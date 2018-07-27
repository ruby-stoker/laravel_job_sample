<?php

namespace App\Jobs;

use App\Models\ {
    Code, Batch, CodeProduct
};
use App\Events\BatchStatusUpdated;
use App\Services\DataTransferService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{
    SerializesModels, InteractsWithQueue
};

class AssignCodeToProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batch;

    protected $step = 100;

    /**
     * Create a new job instance.
     *
     * @param Batch $batch
     */
    public function __construct(Batch $batch)
    {
        $this->batch = $batch;
    }

    /**
     * Execute the job.
     *
     * @param DataTransferService $dataTransferService
     *
     * @return void
     */
    public function handle(DataTransferService $dataTransferService)
    {

        foreach ($this->batch->products as $product) {

            $existCount = $this->batch->assignedCodes()->byProduct($product->id)->count();

            $remainCount = $product->pivot->quantity - $existCount;

            if (!$remainCount || $remainCount < 0) {
                continue;
            }

            // take available codes from db
            $codesSource = Code::available()->take($remainCount);

            $codes = $codesSource->cursor();

            $now = Carbon::now();

            $info = [
                'product_id' => $product->id,
                'brand_id' => $this->batch->brand->id,
                'batch_id' => $this->batch->id,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $values = [];
            $i = 0;

            // insert codes for product
            foreach ($codes as $code) {
                $values[] = array_merge(['code_id' => $code->id], $info);

                if ($i % $this->step == 0 && $i != 0) {
                    CodeProduct::insert($values);
                    unset ($values);
                    $values = [];
                    $i = 0;
                } else {
                    $i++;
                }
            }

            if ($values) {
                CodeProduct::insert($values);
                unset ($values);
            }

            $codesSource->update(['status' => Code::STATUS_ASSIGNED]);

        }

        // if all codes are assigned
        if ($this->batch->assignedCodes->count() == $this->batch->products()->sum('quantity')) {
            // export to CSV
            $dataTransferService->export($this->batch);

            $this->batch->update(['status' => Batch::STATUS_ASSIGNED]);

            // broadcasting batch to socket
            broadcast(new BatchStatusUpdated($this->batch));
        } else {
            self::dispatch($this->batch)->delay(30);
        }
    }

    /**
     * The job failed to process.
     *
     * @param  Exception $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        // Send user notification of failure, etc...
    }
}
