<?php

namespace Novay\Logify\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait LogActivity
{
    /**
     * Mencatat peristiwa pembuatan data baru.
     *
     * @param string $moduleName Nama modul yang melakukan perubahan.
     * @param array<string, mixed> $createdData Data dari record yang baru dibuat.
     * @return void
     */
    protected function logCreated(string $moduleName, array $createdData): void
    {
        $this->log($moduleName, 'Created!', 'info', [
            'created_data' => $createdData
        ]);
    }

    /**
     * Mencatat peristiwa pembaruan data, hanya menampilkan field yang berubah.
     *
     * @param string $moduleName Nama modul yang melakukan perubahan.
     * @param array<string, mixed> $oldData Data dari record sebelum diperbarui.
     * @param array<string, mixed> $newData Data dari record setelah diperbarui.
     * @return void
     */
    protected function logUpdated(string $moduleName, array $oldData, array $newData): void
    {
        $changesMade = $this->getChanges($oldData, $newData);

        if (!empty($changesMade)) {
            $this->log($moduleName, 'Updated!', 'notice', [
                'changed_data' => $changesMade
            ]);
        }
    }

    /**
     * Mencatat peristiwa penghapusan data.
     *
     * @param string $moduleName Nama modul yang melakukan perubahan.
     * @param array<string, mixed> $deletedData Data dari record yang dihapus.
     * @return void
     */
    protected function logDeleted(string $moduleName, array $deletedData): void
    {
        $this->log($moduleName, 'Deleted!', 'error', [
            'deleted_data' => $deletedData
        ]);
    }

    /**
     * Mencatat pesan log secara umum dan fleksibel.
     *
     * @param string $moduleName Nama modul yang melakukan aksi.
     * @param string $message Pesan log.
     * @param string $level Level log (e.g., 'info', 'error', 'warning').
     * @param array<string, mixed> $context Data tambahan untuk log.
     * @return void
     */
    protected function log(string $moduleName, string $message, string $level = 'info', array $context = []): void
    {
        $logContext = $this->getCauserContext();

        Log::channel(config('logify.channel.name'))->{$level}(
            "{$moduleName}: {$message}",
            array_merge($logContext, $context)
        );
    }

    /**
     * Mencatat peristiwa error.
     *
     * @param string $moduleName Nama modul yang melakukan aksi.
     * @param string $message Pesan error.
     * @param array<string, mixed> $context Data tambahan untuk log.
     * @return void
     */
    protected function logError(string $moduleName, string $message, array $context = []): void
    {
        $this->log($moduleName, $message, 'error', $context);
    }

    /**
     * Mengembalikan konteks pengguna yang melakukan aksi.
     *
     * @return array<string, array<string, string|int|null>>
     */
    protected function getCauserContext(): array
    {
        return [
            'user' => [
                'id'   => Auth::id(),
                'name' => optional(Auth::user())->name ?? 'Guest',
                'ip'   => request()->ip() ?? 'N/A',
            ],
        ];
    }

    /**
     * Membandingkan data lama dan baru untuk menemukan perubahan.
     *
     * @param array<string, mixed> $oldData Data dari record sebelum diperbarui.
     * @param array<string, mixed> $newData Data dari record setelah diperbarui.
     * @return array<string, array<string, mixed>>
     */
    protected function getChanges(array $oldData, array $newData): array
    {
        $changesMade = [];
        foreach ($newData as $key => $newValue) {
            // Perbandingan ketat untuk memastikan tidak ada perubahan type
            if (array_key_exists($key, $oldData) && $oldData[$key] !== $newValue) {
                $changesMade[$key] = [
                    'before' => $oldData[$key] ?? null,
                    'after'  => $newValue,
                ];
            }
        }
        return $changesMade;
    }
}