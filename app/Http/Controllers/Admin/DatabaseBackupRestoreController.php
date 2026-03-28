<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DatabaseBackupRestoreController extends Controller
{
    public function store(Request $request, DatabaseBackupService $databaseBackupService): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasPermission('settings.database_backups.manage'),
            403,
        );

        $validated = $request->validate([
            'restore_backup_file' => [
                'required',
                'file',
                'mimetypes:application/sql,application/x-sql,text/plain,text/x-sql,application/octet-stream',
                'mimes:sql,txt',
            ],
            'restore_confirmation' => ['required', 'string', 'in:RESTORE'],
        ], [
            'restore_backup_file.required' => 'اختر ملف النسخة الاحتياطية أولًا.',
            'restore_backup_file.file' => 'الملف المرفوع غير صالح.',
            'restore_backup_file.mimetypes' => 'ارفع ملف SQL أو TXT صالح للاستعادة.',
            'restore_backup_file.mimes' => 'صيغة الملف يجب أن تكون .sql أو .txt.',
            'restore_confirmation.in' => 'اكتب RESTORE بالكامل قبل بدء الاستعادة.',
        ]);

        $file = $validated['restore_backup_file'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'sql');
        $filename = now()->format('Ymd_His') . '_restore_' . Str::random(8) . '.' . $extension;
        $path = $file->storeAs(DatabaseBackupService::BACKUP_DIRECTORY . '/uploads', $filename, 'local');

        $result = $databaseBackupService->restoreUploadedBackup($path);

        return redirect()
            ->route('filament.admin.pages.database-backups-page')
            ->with('database_restore_status', [
                'type' => 'success',
                'title' => 'تمت استعادة النسخة الاحتياطية',
                'message' => $result['safety_backup']
                    ? 'تمت الاستعادة بنجاح مع إنشاء نسخة أمان تلقائية قبل التنفيذ.'
                    : 'تمت الاستعادة بنجاح.',
            ]);
    }
}
