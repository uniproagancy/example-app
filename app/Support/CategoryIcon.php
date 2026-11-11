<?php

namespace App\Support;

class CategoryIcon
{
    public const RULES = [
        'security' => ['მონიტორინგ', 'უსაფრთხოებ', 'ვიდეოთვალ', 'security', 'cctv', 'surveillance'],
        'smart' => ['ჭკვიან', 'სმარტ სახლ', 'smart home'],
        'car' => ['მანქან', 'ავტომობ', 'ავტო ', 'car ', 'automotive'],
        'scooter' => ['ტრანსპორტ', 'სკუტერ', 'ველოსიპედ', 'scooter', 'e-bike', 'bicycle', 'transport'],
        'tools' => ['ხელსაწყო', 'tools', 'drill', 'hardware'],
        'kitchen' => ['სამზარეულო', 'ჭურჭელ', 'kitchen', 'cookware'],
        'small' => ['მსხვილი', 'საყოფაცხოვრებო', 'major appliance', 'large appliance', 'მაცივ', 'სარეცხ'],
        'kettle' => ['წვრილი', 'მცირე საოჯახო', 'საოჯახო', 'small appliance', 'small domestic'],
        'tv' => ['ტელევიზ', 'აუდიო', 'television', ' tv', 'tv ', 'audio'],
        'mobile' => ['მობილურ', 'ტელეფონ', 'სმარტფონ', 'phone', 'mobile'],
        'computers' => ['კომპიუტერ', 'ლეპტოპ', 'ნოუთბუქ', 'computer', 'laptop', 'notebook'],
        'headphones' => ['ყურსასმენ', 'headphone', 'earbud'],
        'gaming' => ['გეიმინგ', 'კონსოლ', 'game', 'gaming', 'console'],
        'photo' => ['ფოტო', 'ვიდეო', 'კამერ', 'photo', 'camera', 'video'],
        'beauty' => ['თავის მოვლა', 'სილამაზ', 'პირადი ჰიგიენ', 'beauty', 'personal care', 'grooming'],
        'cleaning' => ['სახლის', 'ეზო', 'დასუფთავ', 'მტვერსასრუტ', 'cleaning', 'garden', 'home care'],
        'baby' => ['ბავშვ', 'მშობელ', 'ჩვილ', 'baby', 'kids', 'parent'],
        'furniture' => ['ავეჯ', 'furniture'],
        'toys' => ['სათამაშო', 'toy'],
        'newyear' => ['ახალი წელ', 'საახალწლო', 'ნაძვ', 'christmas', 'new year'],
        'acc' => ['აქსესუარ', 'accessor'],
    ];

    public static function guess(string ...$names): string
    {
        $haystack = ' '.mb_strtolower(implode(' ', array_filter($names))).' ';

        foreach (self::RULES as $icon => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $icon;
                }
            }
        }

        return 'default';
    }
}
