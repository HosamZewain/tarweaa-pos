<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\DatabaseBackupService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupsPage extends Page
{
    use HasPagePermission;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'نسخ قاعدة البيانات';
    protected static ?string $title = 'النسخ الاحتياطية والاستعادة';
    protected static ?int $navigationSort = 20;
    protected static string $permissionName = 'settings.database_backups.manage';

    protected string $view = 'filament.pages.database-backups-page';

    public ?string $reset_confirmation = null;
    public array $backupFiles = [];
    public array $resetSummary = [];

    public function mount(): void
    {
        $this->refreshBackupFiles();
        $this->resetSummary = app(DatabaseBackupService::class)->getOperationalResetSummary();
    }

    public function createBackup(): BinaryFileResponse
    {
        $backup = app(DatabaseBackupService::class)->createBackup();

        $this->refreshBackupFiles();

        Notification::make()
            ->title('تم إنشاء النسخة الاحتياطية')
            ->body('تم تجهيز نسخة SQL جديدة لقاعدة البيانات ويمكن تنزيلها الآن.')
            ->success()
            ->send();

        return response()->download(
            storage_path('app/private/' . $backup['path']),
            $backup['filename'],
            ['Content-Type' => 'application/sql'],
        );
    }

    public function downloadBackup(string $path): BinaryFileResponse
    {
        abort_unless(
            collect($this->backupFiles)->contains(fn (array $backup) => $backup['path'] === $path),
            404,
        );

        return response()->download(
            storage_path('app/private/' . $path),
            basename($path),
            ['Content-Type' => 'application/sql'],
        );
    }

    public function refreshBackupFiles(): void
    {
        $this->backupFiles = app(DatabaseBackupService::class)
            ->listBackups()
            ->map(fn (array $backup) => [
                'path' => $backup['path'],
                'name' => $backup['name'],
                'size' => $backup['size'],
                'last_modified_human' => $backup['last_modified']->translatedFormat('Y/m/d h:i A'),
                'size_human' => $this->formatBytes($backup['size']),
            ])
            ->all();
    }

    public function resetOperationalData(): void
    {
        if ($this->reset_confirmation !== 'RESET') {
            Notification::make()
                ->title('تأكيد الحذف غير صحيح')
                ->body('اكتب RESET بالكامل قبل بدء إعادة تهيئة البيانات التشغيلية.')
                ->danger()
                ->send();

            return;
        }

        $result = app(DatabaseBackupService::class)->resetOperationalData(createSafetyBackup: true);

        Notification::make()
            ->title('تمت إعادة تهيئة البيانات التشغيلية')
            ->body(
                $result['safety_backup']
                    ? 'تم حذف البيانات التشغيلية بنجاح مع إنشاء نسخة أمان تلقائية قبل التنفيذ.'
                    : 'تم حذف البيانات التشغيلية بنجاح.'
            )
            ->success()
            ->send();

        $this->reset_confirmation = null;
        $this->refreshBackupFiles();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return number_format($bytes) . ' B';
    }
}
