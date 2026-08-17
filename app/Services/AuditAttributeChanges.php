<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Bentuk before/after yang konsisten untuk perubahan atribut model kecil.
 *
 * Hanya atribut yang benar-benar dirty dicatat. Dengan begitu sebuah klik
 * simpan tanpa perubahan tidak menghasilkan jejak audit palsu yang terlihat
 * seperti sebuah aksi baru.
 */
final class AuditAttributeChanges
{
    /**
     * Dipanggil setelah `fill()` dan sebelum `save()`.
     *
     * @param  array<string, string|array{label: string, nilai?: callable(mixed): string}>  $fields
     * @return array{before: array<string, string>, after: array<string, string>}
     */
    public function fromDirty(Model $model, array $fields): array
    {
        $dirty = $model->getDirty();
        $original = $model->getRawOriginal();
        $before = [];
        $after = [];

        foreach ($dirty as $attribute => $newValue) {
            if (! array_key_exists($attribute, $fields)) {
                continue;
            }

            $field = $fields[$attribute];
            $label = is_string($field) ? $field : $field['label'];
            $format = is_string($field) ? $this->text(...) : ($field['nilai'] ?? $this->text(...));

            $before[$label] = $format($original[$attribute] ?? null);
            $after[$label] = $format($newValue);
        }

        return ['before' => $before, 'after' => $after];
    }

    private function text(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
