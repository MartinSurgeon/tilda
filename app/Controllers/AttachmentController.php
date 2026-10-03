<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\Services\TicketPolicy;
use App\Services\Uploads;

/** Serves attachments from outside the web root, after a permission check. */
final class AttachmentController
{
    public function show(int $id): void
    {
        $a = DB::one('SELECT * FROM ticket_attachments WHERE id = ?', [$id]);
        $t = $a ? Ticket::find((int) $a['ticket_id']) : null;
        $user = Auth::user();

        if (!$a || !$t || !TicketPolicy::canView($t, $user)
            || ((int) $a['is_internal'] === 1 && !TicketPolicy::canSeeInternal())) {
            throw new HttpException(404);
        }

        $path = Uploads::path($a['stored_name']);
        if (!is_file($path)) {
            throw new HttpException(404);
        }

        $download = Request::query('download') === '1' || $a['mime_type'] === 'application/pdf';
        AuditLogger::log('ticket.attachment_viewed', 'ticket', $t['id'], "Opened attachment {$a['original_name']}", [
            'attachment_id' => (int) $a['id'],
        ]);

        $ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $a['original_name']);
        header('Content-Type: ' . $a['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline')
            . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($a['original_name']));
        header('X-Content-Type-Options: nosniff');
        // Even if a file were somehow interpreted as a document, it can run nothing.
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }
}
