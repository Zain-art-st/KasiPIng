<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrecedenceRank extends Model
{
    public const DATA_FILE = 'data/kekananan.txt';

    public const SOURCES_DIR = 'precedence-sources';

    public const CSV_HEADER = ['code', 'title', 'holder', 'aliases'];

    protected $fillable = ['sort', 'code', 'title', 'holder', 'aliases'];

    public function isCategory(): bool
    {
        return filled($this->code);
    }

    public function label(): string
    {
        if ($this->isCategory()) {
            return $this->code.'. '.$this->title;
        }

        return $this->title.($this->holder ? ' — '.$this->holder : '');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.trim($term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('title', 'like', $like)
            ->orWhere('holder', 'like', $like)
            ->orWhere('aliases', 'like', $like)
            ->orWhere('code', trim($term)));
    }

    public static function bestMatch(?string $text): ?self
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $lower = mb_strtolower($text);

        $exact = static::orderBy('sort')->get()->first(fn (self $r) => mb_strtolower($r->title) === $lower
            || mb_strtolower((string) $r->holder) === $lower
            || $r->code === $text
            || in_array($lower, array_map(fn ($a) => mb_strtolower(trim($a)), explode(',', (string) $r->aliases)), true));

        return $exact ?? static::query()->where('title', 'like', '%'.$text.'%')->orderBy('sort')->first();
    }

    public static function options(?string $search = null, int $limit = 50): array
    {
        return static::query()
            ->when($search, fn ($q) => $q->search($search))
            ->orderBy('sort')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (self $r) => [$r->id => $r->label()])
            ->all();
    }

    public static function rowsFromDataFile(): array
    {
        $rows = [];

        foreach (file(database_path(self::DATA_FILE), FILE_IGNORE_NEW_LINES) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line)) + [null, null, null, null];
            $rows[] = [
                'code' => $parts[0] ?: null,
                'title' => $parts[1],
                'holder' => $parts[2] ?: null,
                'aliases' => $parts[3] ?: null,
            ];
        }

        return $rows;
    }

    public static function rowsFromCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header ?: []);

        if (! in_array('title', $header, true)) {
            fclose($handle);
            throw new \InvalidArgumentException('The CSV needs a "title" column (columns: code, title, holder, aliases).');
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, array_pad(array_slice($data, 0, count($header)), count($header), null));

            if (trim((string) ($row['title'] ?? '')) === '') {
                continue;
            }

            $rows[] = [
                'code' => trim((string) ($row['code'] ?? '')) ?: null,
                'title' => trim($row['title']),
                'holder' => trim((string) ($row['holder'] ?? '')) ?: null,
                'aliases' => trim((string) ($row['aliases'] ?? '')) ?: null,
            ];
        }

        fclose($handle);

        return $rows;
    }

    public static function importRows(array $rows): int
    {
        return DB::transaction(function () use ($rows) {
            $existing = static::all();
            $kept = [];
            $sort = 0;

            foreach ($rows as $row) {
                $sort += 10;

                $match = $existing->first(fn (self $r) => ! in_array($r->id, $kept, true) && (
                    ($row['code'] && $r->code === $row['code'])
                    || (! $row['code'] && ! $r->code && $r->title === $row['title'] && $r->holder === $row['holder'])
                ));

                $model = $match ?? new static;
                $model->fill($row + ['sort' => $sort])->save();
                $kept[] = $model->id;
            }

            static::whereNotIn('id', $kept)->delete();

            return count($rows);
        });
    }

    public static function renumber(): void
    {
        $sort = 0;

        static::orderBy('sort')->orderBy('id')->get()->each(function (self $r) use (&$sort) {
            $sort += 10;

            if ($r->sort !== $sort) {
                $r->updateQuietly(['sort' => $sort]);
            }
        });
    }

    public static function toCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::CSV_HEADER);

        static::orderBy('sort')->each(fn (self $r) => fputcsv($out, [$r->code, $r->title, $r->holder, $r->aliases]));

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function sourceFiles(): array
    {
        return collect(glob(resource_path(self::SOURCES_DIR.'/*.pdf')) ?: [])
            ->mapWithKeys(fn ($path) => [basename($path) => $path])
            ->all();
    }
}
