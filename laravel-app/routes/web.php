<?php

use App\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Campaign UI (หน้าเว็บที่ Manager ใช้งานจริงผ่าน Browser)
|--------------------------------------------------------------------------
| หมายเหตุ: middleware 'auth' ถูกปลดออกชั่วคราวเพื่อทดสอบระบบ campaign
| ก่อน (ยังไม่มีระบบ login/Breeze ในโปรเจกต์)
| TODO: เมื่อจะใช้งานจริง ให้ครอบ group ด้วย ->middleware('auth') กลับคืน
|       หลังติดตั้งระบบ authentication เรียบร้อยแล้ว
*/

// เด้ง / ไปหน้า campaigns เลย จะได้ไม่เจอ 404 ตอนเปิด localhost:8000
Route::get('/', function () {
    return redirect()->route('campaigns.index');
});

Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');

Route::post('/campaigns/{campaign}/parse', [CampaignController::class, 'parse'])->name('campaigns.parse');
Route::patch('/campaigns/{campaign}/criteria', [CampaignController::class, 'updateCriteria'])->name('campaigns.criteria.update');
Route::post('/campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start');
Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
Route::post('/campaigns/{campaign}/retry', [CampaignController::class, 'retry'])->name('campaigns.retry');
