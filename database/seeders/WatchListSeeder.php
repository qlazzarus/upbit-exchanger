<?php

namespace Database\Seeders;

use App\Models\WatchList;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WatchListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $symbols = [
            'KRW-BTC',
            'KRW-ETH',
            'KRW-XRP',
        ];

        foreach ($symbols as $s) {
            WatchList::updateOrCreate(
                ['symbol' => $s],
                ['enabled' => true]
            );
        }
    }
}
