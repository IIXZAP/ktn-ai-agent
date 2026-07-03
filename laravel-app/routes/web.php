<?php

use App\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Campaign UI (หน้าเว็บที่ Manager ใช้งานจริงผ่าน Browser)
|--------------------------------------------------------------------------
| ต่างจาก routes/api.php ตรงที่กลุ่มนี้คืน Blade view + ใช้ session/CSRF
| ปรับ middleware 'auth' ให้ตรงกับระบบ auth จริงของโปรเจกต์คุณ
*/

Route::middleware('auth')->group(function () {
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');

    Route::post('/campaigns/{campaign}/parse', [CampaignController::class, 'parse'])->name('campaigns.parse');
    Route::patch('/campaigns/{campaign}/criteria', [CampaignController::class, 'updateCriteria'])->name('campaigns.criteria.update');
    Route::post('/campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::post('/campaigns/{campaign}/retry', [CampaignController::class, 'retry'])->name('campaigns.retry');
});
