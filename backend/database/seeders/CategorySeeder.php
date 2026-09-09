<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Billionaire', 'slug' => 'billionaire'],
            ['name' => 'CEO', 'slug' => 'ceo'],
            ['name' => 'Secret Identity', 'slug' => 'secret-identity'],
            ['name' => 'Romance', 'slug' => 'romance'],
            ['name' => 'Revenge', 'slug' => 'revenge'],
            ['name' => 'Family Drama', 'slug' => 'family-drama'],
            ['name' => 'Rags to Riches', 'slug' => 'rags-to-riches'],
            ['name' => 'Second Chance', 'slug' => 'second-chance'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                ['slug' => $category['slug']]
            );
        }
    }
}
