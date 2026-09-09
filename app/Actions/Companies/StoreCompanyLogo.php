<?php

namespace App\Actions\Companies;

use App\Models\Company;
use App\Support\SvgSanitizer;
use App\Support\SvgSymbolInliner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The logo of the issuing company: shown in the application and printed on every
 * document it issues.
 *
 * SVG is preferred — it stays sharp at any size in the PDF — but it is also the
 * one format that can carry code, so it is rewritten by SvgSanitizer before it
 * is stored. Raster files are accepted as they are, after their content is
 * confirmed to be an image.
 */
class StoreCompanyLogo
{
    /** Extensions dompdf and browsers both handle. */
    public const EXTENSIONS = ['svg', 'png', 'jpg', 'jpeg'];

    public const MAX_KILOBYTES = 2048;

    public function __construct(
        private readonly SvgSanitizer $sanitizer,
        private readonly SvgSymbolInliner $inliner,
    ) {}

    /**
     * @throws ValidationException when the file is not the picture it claims to be
     */
    public function handle(Company $company, UploadedFile $file): Company
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::EXTENSIONS, true)) {
            $this->reject('Dozvoljeni formati su SVG, PNG i JPG.');
        }

        $contents = (string) file_get_contents($file->getRealPath());

        $contents = $extension === 'svg'
            ? $this->safeSvg($contents)
            : $this->verifiedRaster($contents);

        $previousPath = $company->logo_path;

        $path = sprintf('%s/%d/%s.%s', Company::LOGO_DIRECTORY, $company->getKey(), Str::ulid(), $extension);

        Storage::disk(Company::LOGO_DISK)->put($path, $contents);

        $company->update(['logo_path' => $path]);

        $this->deleteFile($previousPath);

        return $company;
    }

    public function remove(Company $company): Company
    {
        $path = $company->logo_path;

        $company->update(['logo_path' => null]);

        $this->deleteFile($path);

        return $company;
    }

    /**
     * Cleaned of anything that can run, then rewritten so the PDF engine draws
     * it at the size the file asks for.
     */
    private function safeSvg(string $contents): string
    {
        try {
            return $this->inliner->inline($this->sanitizer->sanitize($contents));
        } catch (InvalidArgumentException $exception) {
            $this->reject('Fajl nije ispravan SVG: '.$exception->getMessage());
        }
    }

    private function verifiedRaster(string $contents): string
    {
        $size = @getimagesizefromstring($contents);

        if ($size === false || ! in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $this->reject('Fajl nije ispravna PNG ili JPG slika.');
        }

        return $contents;
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(Company::LOGO_DISK)->delete($path);
        }
    }

    /**
     * @throws ValidationException
     */
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['logo' => $message]);
    }
}
