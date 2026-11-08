<?php

namespace Tests\SloppyFixtures;

class DuplicateB
{
    public function report(array $invoices): array
    {
        // Same logic as DuplicateA::summarize with different variable names.
        $sum = 0;
        $n = 0;

        foreach ($invoices as $invoice) {
            $sum += $invoice['amount'];
            $n++;
        }

        $avg = $n > 0 ? $sum / $n : 0;
        $text = sprintf('%d orders', $n);

        return ['total' => $sum, 'average' => $avg, 'label' => $text];
    }
}
