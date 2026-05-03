<?php

namespace App\Http\Controllers;

use App\Mail\InventorySummaryMail;
use Illuminate\Http\Response;

class MailPreviewController extends Controller
{
    public function __invoke(): Response
    {
        $html = (new InventorySummaryMail)->render();

        return response($html)->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
