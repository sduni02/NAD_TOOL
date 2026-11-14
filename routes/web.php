<?php

use App\Http\Controllers\MarksheetController;
use App\Http\Controllers\MappingController;
use Illuminate\Support\Facades\Route;

// Marksheet Upload & DB Management
Route::get('/', [MarksheetController::class, 'index'])->name('marksheets.index');
Route::post('/upload', [MarksheetController::class, 'upload'])->name('marksheets.upload');
Route::post('/refresh', [MarksheetController::class, 'refresh'])->name('marksheets.refresh');
Route::post('/set-student-column', [MarksheetController::class, 'setStudentColumn'])->name('marksheets.setStudentColumn');
Route::post('/switch-tr-semester', [MarksheetController::class, 'switchTrSemester'])->name('marksheets.switchTrSemester');

// Mapping Workspace & Export
Route::get('/mapping', [MappingController::class, 'index'])->name('mapping.index');
Route::post('/mapping/upload-template', [MappingController::class, 'uploadTemplate'])->name('mapping.uploadTemplate');
Route::post('/mapping/reset-default', [MappingController::class, 'resetToDefault'])->name('mapping.resetDefault');
Route::post('/mapping/expand', [MappingController::class, 'expandSubjects'])->name('mapping.expandSubjects');
Route::post('/mapping/save-preset', [MappingController::class, 'savePreset'])->name('mapping.savePreset');
Route::post('/mapping/load-preset', [MappingController::class, 'loadPreset'])->name('mapping.loadPreset');
Route::post('/mapping/preview', [MappingController::class, 'preview'])->name('mapping.preview');
Route::post('/mapping/export', [MappingController::class, 'export'])->name('mapping.export');
