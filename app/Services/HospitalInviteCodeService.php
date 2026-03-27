<?php

namespace App\Services;

use App\Models\HospitalInviteCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HospitalInviteCodeService
{
    public function issue(?string $email, ?string $domain, ?Carbon $expiresAt, ?int $issuedByUserId): array
    {
        $code = sprintf('HINV-%s-%s', Str::upper(Str::random(6)), Str::upper(Str::random(6)));

        $record = HospitalInviteCode::query()->create([
            'code_hash' => $this->hashCode($code),
            'email' => $email ? Str::lower(trim($email)) : null,
            'domain' => $domain ? Str::lower(trim($domain)) : null,
            'expires_at' => $expiresAt,
            'issued_by_user_id' => $issuedByUserId,
        ]);

        return [
            'invite' => $record,
            'code' => $code,
        ];
    }

    public function validateAndConsume(string $code, string $email): bool
    {
        $normalizedEmail = Str::lower(trim($email));
        $emailDomain = Str::lower(Str::after($normalizedEmail, '@'));

        $invite = HospitalInviteCode::query()
            ->where('code_hash', $this->hashCode($code))
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $invite) {
            return false;
        }

        if ($invite->email !== null && $invite->email !== $normalizedEmail) {
            return false;
        }

        if ($invite->domain !== null && $invite->domain !== $emailDomain) {
            return false;
        }

        $invite->forceFill([
            'used_at' => now(),
            'used_by_email' => $normalizedEmail,
        ])->save();

        return true;
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', trim($code));
    }
}
