<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/s3-files', function () {
    try {
        $disk = Storage::disk('s3');
        $bucket = config('filesystems.disks.s3.bucket', 'blogapi-excel-reports');
        $endpoint = config('filesystems.disks.s3.endpoint', 'http://127.0.0.1:9090');
        $paths = $disk->allFiles('exports');

        $files = array_map(function ($path) use ($disk) {
            return [
                'name' => basename($path),
                'path' => $path,
                'size' => round($disk->size($path) / 1024, 2),
                'modified' => date('Y-m-d H:i:s', $disk->lastModified($path)),
            ];
        }, $paths);

        $totalCount = count($files);
        $totalKb = array_sum(array_column($files, 'size'));

        $rowsHtml = '';
        if (empty($files)) {
            $rowsHtml = '<tr><td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">No archived files found in bucket.</td></tr>';
        } else {
            foreach ($files as $file) {
                $downloadUrl = url('/s3-files/download/' . urlencode($file['name']));
                $rowsHtml .= "
                    <tr style='border-bottom: 1px solid #1e293b; transition: background 0.2s;'>
                        <td style='padding: 16px; font-weight: 500; color: #f8fafc;'>
                            <span style='margin-right: 8px;'>📊</span> {$file['name']}
                        </td>
                        <td style='padding: 16px; color: #38bdf8;'>{$file['size']} KB</td>
                        <td style='padding: 16px; color: #94a3b8;'>{$file['modified']}</td>
                        <td style='padding: 16px;'>
                            <a href='{$downloadUrl}' style='display: inline-block; background: #2563eb; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500;'>
                                ⬇ Download
                            </a>
                        </td>
                    </tr>
                ";
            }
        }

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>S3 Cloud Storage Explorer - BlogAPI</title>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
            <style>
                body { font-family: 'Inter', sans-serif; background: #0b0f19; color: #e2e8f0; margin: 0; padding: 40px 20px; }
                .container { max-width: 1000px; margin: 0 auto; }
                .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }
                .badge { background: #065f46; color: #34d399; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 6px; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
                .card { background: #131c2e; border: 1px solid #1e293b; border-radius: 12px; padding: 20px; }
                .card-title { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
                .card-value { font-size: 22px; font-weight: 700; color: #f8fafc; margin-top: 6px; }
                table { width: 100%; border-collapse: collapse; background: #131c2e; border: 1px solid #1e293b; border-radius: 12px; overflow: hidden; }
                th { background: #1e293b; color: #94a3b8; text-align: left; padding: 14px 16px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
                tr:hover { background: #172339; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div>
                        <div class='title'>
                            <span>🪣</span> S3 Cloud Storage Explorer
                        </div>
                        <p style='color: #94a3b8; font-size: 14px; margin-top: 4px;'>Visual management dashboard for your Docker S3 Cloud Bucket</p>
                    </div>
                    <div>
                        <span class='badge'><span style='width: 8px; height: 8px; background: #34d399; border-radius: 50%;'></span> Connected: Docker S3</span>
                    </div>
                </div>

                <div class='stats-grid'>
                    <div class='card'>
                        <div class='card-title'>Bucket Name</div>
                        <div class='card-value' style='color: #60a5fa;'>{$bucket}</div>
                    </div>
                    <div class='card'>
                        <div class='card-title'>Total Archived Files</div>
                        <div class='card-value'>{$totalCount}</div>
                    </div>
                    <div class='card'>
                        <div class='card-title'>Total Storage Used</div>
                        <div class='card-value'>{$totalKb} KB</div>
                    </div>
                    <div class='card'>
                        <div class='card-title'>Endpoint</div>
                        <div class='card-value' style='font-size: 16px; font-weight: 500; color: #94a3b8;'>{$endpoint}</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Uploaded At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rowsHtml}
                    </tbody>
                </table>
            </div>
        </body>
        </html>
        ";
    } catch (\Throwable $e) {
        return response("<h3>S3 Connection Error: " . htmlspecialchars($e->getMessage()) . "</h3>", 500);
    }
});

Route::get('/s3-files/download/{filename}', function (string $filename) {
    $path = 'exports/' . basename($filename);
    if (! Storage::disk('s3')->exists($path)) {
        abort(404, 'File not found in S3 bucket.');
    }
    return Storage::disk('s3')->download($path, basename($filename));
});


