<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }

        $disk = Storage::disk('public');

        DB::table('kamar_fotos')
            ->whereNotNull('path')
            ->orderBy('id')
            ->get(['id', 'kamar_id', 'path'])
            ->each(function ($photo) use ($disk) {
                $newPath = $this->convertToJpg($disk, $photo->path);
                if (! $newPath || $newPath === $photo->path) {
                    return;
                }

                DB::table('kamar_fotos')->where('id', $photo->id)->update([
                    'path' => $newPath,
                    'updated_at' => now(),
                ]);

                DB::table('kamars')->where('id', $photo->kamar_id)->where('foto', $photo->path)->update([
                    'foto' => $newPath,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        //
    }

    private function convertToJpg($disk, ?string $path): ?string
    {
        if (! $path || Str::endsWith(Str::lower($path), ['.jpg', '.jpeg']) || ! $disk->exists($path)) {
            return $path;
        }

        $source = @imagecreatefromstring($disk->get($path));
        if (! $source) {
            return $path;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxSide = 960;
        $scale = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        $white = imagecolorallocate($target, 255, 255, 255);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($target, null, 72);
        $contents = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        if (! $contents) {
            return $path;
        }

        $newPath = 'balikos/kamar/'.Str::uuid().'.jpg';
        $disk->put($newPath, $contents);

        return $newPath;
    }
};
