<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Số mã khôi phục cấp mỗi lần (mỗi mã dùng được một lần). */
    public const RECOVERY_CODE_COUNT = 8;

    /** Chữ cái của mã khôi phục: bỏ 0/o, 1/l/i cho khỏi đọc nhầm khi chép tay. */
    private const RECOVERY_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    public function recoveryCodesLeft(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    /**
     * Cấp bộ mã khôi phục mới (xoá bộ cũ). Trả về mã GỐC để hiện cho người dùng đúng một lần;
     * trong DB chỉ giữ HMAC của chúng.
     *
     * @return list<string>
     */
    public function issueRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $raw = '';
            for ($j = 0; $j < 10; $j++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
            }
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        $this->two_factor_recovery_codes = array_map(self::hashRecoveryCode(...), $codes);

        return $codes;
    }

    /** Dùng một mã khôi phục: đúng thì gạch khỏi danh sách và trả true. */
    public function consumeRecoveryCode(string $input): bool
    {
        $needle = self::hashRecoveryCode($input);
        $hashes = $this->two_factor_recovery_codes ?? [];

        foreach ($hashes as $i => $hash) {
            if (hash_equals($hash, $needle)) {
                unset($hashes[$i]);
                $this->two_factor_recovery_codes = array_values($hashes);
                $this->save();

                return true;
            }
        }

        return false;
    }

    /** Gõ hoa/thường, có hay không dấu gạch, dấu cách đều được. */
    private static function hashRecoveryCode(string $code): string
    {
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $code));

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
