<?php

use App\Http\Controllers\Internal\InternalCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal Callback (Python -> Laravel)
|--------------------------------------------------------------------------
| ป้องกันด้วย VerifyPythonAgentSecret middleware (Step 10)
| ต้องมี Header Authorization: Bearer <PYTHON_AGENT_SECRET> เท่านั้นถึงเรียกได้
| ตรงกับ 8 endpoint ที่ Python ส่ง callback มา (Step 5 / Step 8)
|
| หมายเหตุ: route ของ Campaign UI (Manager -> Laravel) ย้ายไปอยู่ใน
| routes/web.php แล้ว เพราะ CampaignController คืนค่าเป็น Blade view
| + redirect (ใช้ session/CSRF) ไม่ใช่ JSON API เหมือนตอนแรก
*/

Route::middleware('verify.python.secret')
    ->prefix('internal')
    ->group(function () {
        Route::post('job-progress', [InternalCallbackController::class, 'jobProgress']);
        Route::post('research-results', [InternalCallbackController::class, 'researchResults']);
        Route::post('website-results', [InternalCallbackController::class, 'websiteResults']);
        Route::post('crawl-results', [InternalCallbackController::class, 'crawlResults']);
        Route::post('ai-analysis-results', [InternalCallbackController::class, 'aiAnalysisResults']);
        Route::post('lead-scores', [InternalCallbackController::class, 'leadScores']);
        Route::post('job-completed', [InternalCallbackController::class, 'jobCompleted']);
        Route::post('job-failed', [InternalCallbackController::class, 'jobFailed']);
    });
