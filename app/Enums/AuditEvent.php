<?php

namespace App\Enums;

/**
 * What an audit log entry records, grouped into categories for filtering.
 */
enum AuditEvent: string
{
    // Changes to records
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    // Access to personal data
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Exported = 'exported';

    // Sign-in and access control
    case LoggedIn = 'logged-in';
    case LoggedOut = 'logged-out';
    case LoginFailed = 'login-failed';
    case LockedOut = 'locked-out';
    case PasswordReset = 'password-reset';
    case AccessDenied = 'access-denied';

    // System administration
    case BackupCreated = 'backup-created';
    case BackupDownloaded = 'backup-downloaded';
    case BackupUploaded = 'backup-uploaded';
    case BackupRestored = 'backup-restored';
    case BackupDeleted = 'backup-deleted';

    public const CATEGORIES = ['changes', 'access', 'security', 'system'];

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Viewed => 'Viewed',
            self::Downloaded => 'Downloaded',
            self::Exported => 'Exported',
            self::LoggedIn => 'Signed in',
            self::LoggedOut => 'Signed out',
            self::LoginFailed => 'Failed sign-in',
            self::LockedOut => 'Locked out',
            self::PasswordReset => 'Password reset',
            self::AccessDenied => 'Access denied',
            self::BackupCreated => 'Backup created',
            self::BackupDownloaded => 'Backup downloaded',
            self::BackupUploaded => 'Backup uploaded',
            self::BackupRestored => 'Backup restored',
            self::BackupDeleted => 'Backup deleted',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Created, self::Updated, self::Deleted => 'changes',
            self::Viewed, self::Downloaded, self::Exported => 'access',
            self::LoggedIn, self::LoggedOut, self::LoginFailed, self::LockedOut, self::PasswordReset, self::AccessDenied => 'security',
            default => 'system',
        };
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'changes' => __('Changes to records'),
            'access' => __('Access to personal data'),
            'security' => __('Sign-ins and denied access'),
            'system' => __('Backups and restores'),
        };
    }

    /**
     * Whether the entry is a warning sign worth highlighting.
     */
    public function isSecurityWarning(): bool
    {
        return in_array($this, [self::LoginFailed, self::LockedOut, self::AccessDenied], true);
    }

    public function badgeColor(): string
    {
        return match (true) {
            $this->isSecurityWarning(), $this === self::Deleted, $this === self::BackupRestored => 'red',
            $this->category() === 'changes' => 'brand',
            $this->category() === 'access' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * @return list<self>
     */
    public static function inCategory(string $category): array
    {
        return array_values(array_filter(self::cases(), fn (self $event): bool => $event->category() === $category));
    }
}
