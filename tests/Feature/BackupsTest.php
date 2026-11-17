<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BackupTargets\BackupTargetResource;
use App\Filament\Admin\Resources\BackupTargets\Pages\CreateBackupTarget;
use App\Filament\Admin\Resources\BackupTargets\Pages\ListBackupTargets;
use App\Filament\Admin\Resources\BackupTargets\Pages\ViewBackupTarget;
use App\Models\Backup;
use App\Models\BackupTarget;
use App\Models\User;
use App\Support\Backups\BackupManager;
use App\Support\Backups\DatabaseDumper;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/**
 * System backups to backup targets (separate from the NAS systems of the documents) and restoring any of them.
 */
class BackupsTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = sys_get_temp_dir().'/power-erp-backup-test-'.uniqid();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    protected function target(array $attributes = []): BackupTarget
    {
        return BackupTarget::create([
            'name' => 'Lokaal', 'type' => 'local', 'host' => 'localhost', 'path' => $this->folder,
            'parts' => ['database'], 'frequency' => 'daily', 'time' => '00:00', 'keep' => 2, 'is_active' => true,
            ...$attributes,
        ]);
    }

    #[Test]
    public function targets_are_managed_and_tested_by_administrators_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateBackupTarget::class)
            ->fillForm(['name' => 'Synology', 'type' => 'synology', 'host' => 'nas.local', 'username' => 'backup', 'password' => 'geheim', 'parts' => ['database'], 'path' => null])
            ->call('create')
            ->assertHasFormErrors(['path' => 'required']);

        Livewire::test(CreateBackupTarget::class)
            ->fillForm(['name' => 'Lokale schijf', 'type' => 'local', 'path' => $this->folder, 'parts' => ['database', 'files'], 'frequency' => 'daily', 'time' => '02:00', 'keep' => 7])
            ->call('create')
            ->assertHasNoFormErrors();

        $target = BackupTarget::query()->sole();
        $this->assertSame('localhost', $target->host);
        $this->assertSame(['database', 'files'], $target->parts);

        Livewire::test(ViewBackupTarget::class, ['record' => $target->getRouteKey()])
            ->callAction('test')
            ->assertNotified(__('backups.test_ok'));
        Livewire::test(ListBackupTargets::class)->assertCanSeeTableRecords([$target]);
        $this->get('/admin/backups')->assertOk()->assertSee('Lokale schijf');

        // The password is stored encrypted and never shown.
        $synology = $this->target(['name' => 'Syno', 'type' => 'synology', 'host' => 'nas.local', 'username' => 'backup', 'password' => 'geheim', 'path' => '/backup/power-erp']);
        $this->assertNotSame('geheim', \DB::table('backup_targets')->where('id', $synology->id)->value('password'));
        $this->assertArrayNotHasKey('password', $synology->toArray());

        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create())->get('/admin/backups')->assertForbidden();
    }

    #[Test]
    public function a_backup_holds_the_database_and_old_backups_are_rotated(): void
    {
        $target = $this->target();
        User::factory()->create(['email' => 'in-de-backup@example.com']);

        foreach (range(1, 3) as $minute) {
            $this->travel(1)->minutes();
            $backup = app(BackupManager::class)->run($target);
            $this->assertSame('done', $backup->status, (string) $backup->message);
        }

        $files = $target->transport()->list();
        $this->assertCount(2, $files);
        $this->assertStringStartsWith('power-erp-backup-', $files[0]['name']);

        $zip = new ZipArchive;
        $zip->open($this->folder.'/'.$files[0]['name']);
        $this->assertSame(['database'], json_decode($zip->getFromName('backup.json'), true)['parts']);
        $sql = (string) $zip->getFromName('database.sql');
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('in-de-backup@example.com', $sql);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);
        $zip->close();

        $this->assertNotNull($target->fresh()->last_run_at);
        $this->assertFalse($target->fresh()->isDue());
        $this->assertSame(3, Backup::query()->where('kind', 'backup')->where('status', 'done')->count());
    }

    #[Test]
    public function any_backup_on_the_target_can_be_restored_after_typing_restore(): void
    {
        $this->withoutDefer();
        $admin = User::factory()->admin()->create();
        $target = $this->target(['keep' => 5]);
        app(BackupManager::class)->run($target);
        $this->travel(1)->minutes();
        app(BackupManager::class)->run($target);
        [$newest, $oldest] = array_column($target->transport()->list(), 'name');
        $this->actingAs($admin);

        // Both are offered, newest first.
        $this->assertSame([$newest, $oldest], array_keys(BackupTargetResource::fileOptions($target)));
        Livewire::test(ViewBackupTarget::class, ['record' => $target->getRouteKey()])->assertSee($oldest);

        $this->mock(BackupManager::class, fn (MockInterface $mock) => $mock->shouldReceive('restore')->once()
            ->withArgs(fn (BackupTarget $on, string $file, array $parts, ?User $user, bool $withEnv): bool => $on->is($target) && $file === $oldest && $parts === ['database', 'files'] && $user?->is($admin) && ! $withEnv)
            ->andReturn(new Backup));

        Livewire::test(ViewBackupTarget::class, ['record' => $target->getRouteKey()])
            ->callAction('restore', ['file' => $oldest, 'parts' => ['database', 'files'], 'confirm' => 'restore'])
            ->assertHasActionErrors(['confirm']);

        Livewire::test(ViewBackupTarget::class, ['record' => $target->getRouteKey()])
            ->callAction('restore', ['file' => '../../.env', 'parts' => ['database'], 'confirm' => 'RESTORE'])
            ->assertHasActionErrors(['file']);

        Livewire::test(ViewBackupTarget::class, ['record' => $target->getRouteKey()])
            ->callAction('restore', ['file' => $oldest, 'parts' => [], 'with_env' => false, 'confirm' => 'RESTORE'])
            ->assertNotified(__('backups.choose_parts'));

        Livewire::test(ViewBackupTarget::class, ['record' => $target->getRouteKey()])
            ->callAction('restore', ['file' => $oldest, 'parts' => ['database', 'files'], 'confirm' => 'RESTORE'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('backups.restore_started'));
    }

    #[Test]
    public function only_backups_of_the_platform_are_restored(): void
    {
        $other = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($other, ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', 'DROP TABLE users;');
        $zip->close();

        $restore = app(BackupManager::class)->restore($this->target(), 'not-a-backup.zip', ['database']);
        $this->assertSame('failed', $restore->status);
        $this->assertSame('This is not a backup of the platform.', $restore->message);

        $this->expectExceptionMessage('This is not a backup of the platform.');
        app(BackupManager::class)->restoreFile($other, ['database']);
    }

    #[Test]
    public function the_dump_can_be_read_back_statement_by_statement(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql');
        app(DatabaseDumper::class)->dump($path);
        $sql = (string) file_get_contents($path);
        @unlink($path);

        $this->assertStringStartsWith('-- Power ERP database backup', $sql);
        $this->assertStringContainsString("SET FOREIGN_KEY_CHECKS=0;\n", $sql);
        $this->assertMatchesRegularExpression('/^CREATE TABLE `projects` .+;$/m', $sql);
    }

    #[Test]
    public function the_scheduler_runs_the_targets_that_are_due(): void
    {
        $this->target(['frequency' => 'manual']);
        $due = $this->target(['name' => 'Nachtelijk']);

        $this->artisan('backups:run')->assertSuccessful();

        $this->assertSame([$due->id], Backup::query()->pluck('backup_target_id')->all());
    }
}
