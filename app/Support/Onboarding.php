<?php

namespace App\Support;

use App\Models\OnboardingDocument;
use App\Models\OnboardingSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The onboarding of a user: the active documents in their language (mother tongue first), which they open and sign one by
 * one. When all are signed the onboarding is complete; until then a new user is sent to the onboarding
 * page first. Users that existed when the module came count as onboarded.
 */
class Onboarding
{
    /** @var array<int, bool> per user, for one request */
    protected array $needs = [];

    public function enabled(): bool
    {
        return modules()->onboarding();
    }

    /**
     * The language of the user's documents: their mother tongue (employee register), else the language
     * they use the platform in, else Dutch; the first of those that has documents.
     */
    public function locale(User $user): string
    {
        $candidates = array_values(array_unique(array_filter([
            $user->employee()->value('mother_tongue'),
            $user->preferredLocale(),
            'nl',
        ], fn (?string $locale): bool => array_key_exists((string) $locale, OnboardingDocument::LOCALES))));

        $available = OnboardingDocument::query()->where('is_active', true)->whereIn('locale', $candidates)->distinct()->pluck('locale')->all();

        return collect($candidates)->first(fn (string $locale): bool => in_array($locale, $available, true)) ?? $candidates[0];
    }

    /**
     * @return Collection<int, OnboardingDocument>
     */
    public function documentsFor(User $user): Collection
    {
        return OnboardingDocument::query()->for($this->locale($user))->get();
    }

    /**
     * The user's row per document (opened, signed), by document id.
     *
     * @return Collection<int, OnboardingSignature>
     */
    public function signaturesFor(User $user): Collection
    {
        return OnboardingSignature::query()->where('user_id', $user->id)->get()->keyBy('onboarding_document_id');
    }

    /**
     * @return Collection<int, OnboardingDocument>
     */
    public function pendingFor(User $user): Collection
    {
        $signed = $this->signaturesFor($user)->filter(fn (OnboardingSignature $signature): bool => $signature->isSigned())->keys()->all();

        return $this->documentsFor($user)->reject(fn (OnboardingDocument $document): bool => in_array($document->id, $signed, true))->values();
    }

    /**
     * Whether the user still has to read and sign documents before using the platform.
     */
    public function needsOnboarding(User $user): bool
    {
        if (! $this->enabled() || $user->onboarding_completed_at !== null) {
            return false;
        }

        return $this->needs[$user->id] ??= $this->pendingFor($user)->isNotEmpty();
    }

    /**
     * The user opened the document (needed before signing it).
     */
    public function open(User $user, OnboardingDocument $document): OnboardingSignature
    {
        $signature = OnboardingSignature::query()->firstOrCreate(['user_id' => $user->id, 'onboarding_document_id' => $document->id]);

        if ($signature->opened_at === null) {
            $signature->update(['opened_at' => now()]);
        }

        return $signature;
    }

    /**
     * Signs that the user read the document; the last one completes the onboarding.
     */
    public function sign(User $user, OnboardingDocument $document, string $name, string $dataUrl): void
    {
        $signature = OnboardingSignature::query()->where('user_id', $user->id)->where('onboarding_document_id', $document->id)->first();

        if (! $signature?->opened_at) {
            throw new \DomainException('The document has to be opened before it is signed.');
        }

        if (! $signature->isSigned()) {
            $signature->sign($name, $dataUrl);
        }

        unset($this->needs[$user->id]);

        if ($user->onboarding_completed_at === null && $this->pendingFor($user)->isEmpty()) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }
    }
}
