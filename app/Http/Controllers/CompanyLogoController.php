<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Serves the logo of a company to whoever may see that company.
 *
 * The file never sits in the public directory, and it is handed back under a
 * policy that lets the browser draw it and nothing else — an SVG opened
 * directly in a tab is still a document that could try to run.
 */
class CompanyLogoController extends Controller
{
    public function __invoke(Company $company): Response
    {
        Gate::authorize('view', $company);

        abort_unless($company->hasLogo(), 404);

        return response((string) $company->logoContents(), 200, [
            'Content-Type' => (string) $company->logoMimeType(),
            'Content-Disposition' => 'inline; filename="logo-'.$company->getKey().'"',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
