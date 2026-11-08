<?php

namespace Tests\SloppyFixtures;

class DuplicateA
{
    public function summarize(array $orders): array
    {
        $total = 0;
        $count = 0;

        foreach ($orders as $order) {
            $total += $order['amount'];
            $count++;
        }

        $average = $count > 0 ? $total / $count : 0;
        $label = sprintf('%d orders', $count);

        return ['total' => $total, 'average' => $average, 'label' => $label];
    }
}
