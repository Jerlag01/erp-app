<?php

namespace App\Actions\Batteries;

use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\BaeOrder;
use App\Models\BatterySupplier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Mails a complete BAE order as PDF to its supplier (in the supplier's language), with the supplier's
 * own cc addresses and the copy addresses of Settings > Batteries. Only done with the button on the
 * order, never automatically; replies go to the employee who sent it.
 */
class SendBaeOrder
{
    public function __construct(protected BaeOrderDocument $document) {}

    /**
     * The copy addresses of Settings > Batteries.
     *
     * @return list<string>
     */
    public static function copyAddresses(): array
    {
        return array_values(array_filter(array_map('trim', (array) settings()->get('batteries.bae_order_cc')), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }

    /**
     * @return list<string>
     */
    public static function ccFor(BatterySupplier $supplier): array
    {
        return array_values(array_unique(array_diff([...$supplier->ccEmails(), ...self::copyAddresses()], [$supplier->email])));
    }

    /**
     * "orders@bae.example; cc: purchase@bae.example, office@power.example".
     */
    public static function recipientsText(BatterySupplier $supplier): string
    {
        $cc = self::ccFor($supplier);

        return $supplier->email.($cc !== [] ? '; cc: '.implode(', ', $cc) : '');
    }

    public function handle(BaeOrder $order, User $by, ?string $message = null): void
    {
        $order->loadMissing(['supplier', 'project.workSite', 'project.category', 'batteryModel']);
        $supplier = $order->supplier;

        if (! $order->isComplete()) {
            throw new \DomainException('The BAE order is not complete.');
        }

        if (! $supplier || ! filter_var($supplier->email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('The BAE order has no supplier with an e-mail address.');
        }

        $mail = new SystemMail(MailTemplateKey::BaeOrder, $supplier->mailLocale(), [
            'supplier' => $supplier->name,
            'contact_name' => $supplier->contact_name ?: $supplier->name,
            'order_number' => $order->order_number,
            'order_subject' => $order->subject(),
            'cow_code' => $order->cowLine(),
            'reference' => $order->ourReference(),
            'delivery_date' => $order->delivery_date ?: '—',
            'delivery_address' => $order->delivery_address ?: '—',
            'total' => '€ '.number_format($order->total(), 2, ',', '.'),
            'message' => trim((string) $message),
            'sender' => $by->name,
            'sender_email' => $by->email,
        ], [[
            'data' => $this->document->render($order),
            'name' => BaeOrderDocument::filename($order),
            'mime' => 'application/pdf',
        ]]);

        if (filled($by->email)) {
            $mail->replyTo($by->email, $by->name);
        }

        Mail::to($supplier->email, $supplier->name)->cc(self::ccFor($supplier))->send($mail);

        $order->forceFill([
            'sent_at' => now(),
            'sent_by' => $by->id,
            'sent_to' => self::recipientsText($supplier),
            'exported_at' => $order->exported_at ?? now(),
        ])->saveQuietly();
    }
}
