<?php

namespace App\Services;

use App\Models\Batch;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Chumper\Zipper\Zipper;

class DataTransferService
{

    /**
     * Export data from Batch.
     *
     * @param Batch $batch
     */
    public function export(Batch $batch)
    {

        $zipper = new Zipper();
        $folderName = $batch->hash . '_' . $batch->created_at->format('Y_m_d_H_i');

        foreach ($batch->products as $product) {
            $assignedCodes = $batch->assignedCodes()->byProduct($product->id);
            $sheetName = snake_case($product->name);

            $fileName = $sheetName . '_' . date('Y_m_d_H_i');
            Excel::create($fileName, function ($excel) use ($assignedCodes, $sheetName) {
                $excel->sheet($sheetName, function ($sheet) use ($assignedCodes) {
                    $sheet->appendRow([
                        'Code', 'SKU'
                    ]);
                    $assignedCodes->chunk($this->step, function ($rows) use ($sheet) {
                        foreach ($rows as $row) {
                            $sheet->appendRow([
                                $row->product->prefix . $row->code->code, $row->product->sku
                            ]);
                        }
                    });
                });
            })->store('csv', Storage::disk('downloads')->path("$folderName"));
        }

        $files = glob(Storage::disk('downloads')->path("$folderName/*"));
        $zipper->make(Storage::disk('downloads')->path("$folderName.zip"))->add($files)->close();

    }

}

