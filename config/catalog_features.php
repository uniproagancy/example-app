<?php

return [
    'profiles' => [
        'televisions' => [
            'categories' => ['ტელევიზორი'],
            'kind' => 'screen_cm',
            'attributes' => [
                'ekranis-zoma', 'ეკრანის ზომა', 'ekranis-diagonali', 'ეკრანის დიაგონალი',
                'screen-diagonal', 'diagonal', 'დიაგონალი', 'zoma', 'screen-size', 'ზომა',
                'screen', 'ეკრანი',
            ],
        ],
        'phones' => [
            'categories' => ['მობილური ტელეფონები'],
            'kind' => 'storage',
            'attributes' => [
                'shida-mekhsiereba', 'შიდა მეხსიერება', 'internal-storage', 'built-in-storage',
                'ჩაშენებული მეხსიერება', 'storage-capacity', 'rom',
            ],
            'exclude' => ['operatiuli-mekhsiereba', 'ოპერატიული მეხსიერება', 'ram', 'ram-memory', 'memory-ram'],
        ],
        'climate_equipment' => [
            'categories' => ['კლიმატური ტექნიკა'],
            'kind' => 'area',
            'attributes' => [
                'rekomendirebuli-farti', 'რეკომენდირებული ფართი',
                'rekomendirebuli-fartobi', 'რეკომენდირებული ფართობი',
                'rekomendebuli-farti', 'რეკომენდებული ფართი',
                'fartobi', 'ფართობი', 'farti', 'ფართი',
                'gatbobagagrilebis-fartobi', 'გათბობა/გაგრილების ფართობი',
                'mokmedebis-fartobi', 'მოქმედების ფართობი',
                'gasatbobi-fartobi', 'გასათბობი ფართობი',
                'uncategorized-samushao-fartobi-m2', 'სამუშაო ფართობი (მ²)',
                'gatbobis-fartobi', 'გათბობის ფართობი',
                'recommended-area', 'room-area', 'coverage-area', 'cooling-area', 'heating-area',
            ],
        ],
        'refrigerators' => [
            'categories' => ['მაცივარი', 'საყინულე მაცივარი'],
            'kind' => 'volume',
            'attributes' => [
                'mtliani-motsuloba', 'მთლიანი მოცულობა', 'საერთო მოცულობა', 'ჯამური მოცულობა',
                'მთლიანი სასარგებლო მოცულობა', 'საერთო სასარგებლო მოცულობა',
                'საერთო ნეტო მოცულობა', 'მთლიანი ნეტო მოცულობა',
                'total-volume', 'total-capacity', 'total-net-capacity', 'total-net-volume', 'net-total-volume',
            ],
        ],
        'washing_machines' => [
            'categories' => ['სარეცხი მანქანა'],
            'kind' => 'load',
            'attributes' => [
                'maksimaluri-chatvirtva', 'მაქსიმალური ჩატვირთვა',
                'რეცხვის მოცულობა', 'რეცხვის ტევადობა', 'washing-capacity', 'wash-capacity',
                'ჩატვირთვის მოცულობა', 'load-capacity', 'maximum-load',
            ],
        ],
        'christmas_trees' => [
            'categories' => ['ნაძვის ხეები'],
            'kind' => 'height',
            'attributes' => ['simaghle', 'სიმაღლე', 'height', 'tree-height', 'ნაძვის სიმაღლე'],
        ],
        'laptop_ssd' => [
            'categories' => ['ნოუთბუქი', 'გეიმინგ ლეპტოპი'], 'kind' => 'ssd_storage',
            'attribute_codes' => ['ssd-motsuloba'], 'attributes' => ['ssd-motsuloba'],
        ],
        'dryers' => [
            'categories' => ['საშრობი მანქანა'], 'kind' => 'drying_load',
            'attribute_codes' => ['maksimaluri-datvirtva', 'maksimaluri-chatvirtva', 'maksimaluri-chatvirtva-shroba'],
            'attributes' => ['maksimaluri-datvirtva', 'maksimaluri-chatvirtva', 'maksimaluri-chatvirtva-shroba'],
        ],
        'dishwashers' => [
            'categories' => ['ჭურჭლის სარეცხი მანქანა'], 'kind' => 'place_settings',
            'attribute_codes' => ['personis-raodenoba', 'personebis-raodenoba', 'uncategorized-nakrebis-tevadoba'],
            'attributes' => ['personis-raodenoba', 'personebis-raodenoba', 'uncategorized-nakrebis-tevadoba'],
        ],
        'ovens' => [
            'categories' => ['ჩასაშენებელი ელექტრო ღუმელი', 'ელექტრო ღუმელი'], 'kind' => 'cavity_volume',
            'attribute_codes' => ['motsuloba', 'tevadoba', 'ghumelis-motsuloba'],
            'attributes' => ['motsuloba', 'tevadoba', 'ghumelis-motsuloba'],
        ],
        'microwaves' => [
            'categories' => ['ჩასაშენებელი მიკროტალღური', 'მიკროტალღური ღუმელი'], 'kind' => 'cavity_volume',
            'attribute_codes' => ['shida-motsuloba', 'motsuloba', 'ghumelis-motsuloba'],
            'attributes' => ['shida-motsuloba', 'motsuloba', 'ghumelis-motsuloba'],
        ],
        'extractor_hoods' => [
            'categories' => ['გამწოვი', 'ჩასაშენებელი გამწოვი'], 'kind' => 'air_flow',
            'attribute_codes' => ['tsarmadoba', 'uncategorized-haeris-nakadis-machveneblebi-m3st', 'maksimaluri-produktiulobagatsova'],
            'attributes' => ['tsarmadoba', 'uncategorized-haeris-nakadis-machveneblebi-m3st', 'maksimaluri-produktiulobagatsova'],
            'unit_from_name_codes' => ['uncategorized-haeris-nakadis-machveneblebi-m3st'],
        ],
        'hobs' => [
            'categories' => ['ჩასაშენებელი ქურა'], 'kind' => 'cooking_zones',
            'attribute_codes' => ['sakhurebeli-zonebis-raodenoba'], 'attributes' => ['sakhurebeli-zonebis-raodenoba'],
        ],
        'electric_storage_water_heaters' => [
            'categories' => ['დენის წყლის გამაცხელებელი'], 'kind' => 'volume',
            'attribute_codes' => ['motsuloba', 'tsivi-tsqlis-rezervuaris-motsuloba'],
            'attributes' => ['motsuloba', 'tsivi-tsqlis-rezervuaris-motsuloba'],
        ],
        'air_fryers' => [
            'categories' => ['აეროგრილები'], 'kind' => 'volume',
            'attribute_codes' => ['motsuloba', 'tevadoba', 'kalatis-motsuloba', 'mtliani-motsuloba'],
            'attributes' => ['motsuloba', 'tevadoba', 'kalatis-motsuloba', 'mtliani-motsuloba'],
        ],
        'kettles' => [
            'categories' => ['ელექტრო ჩაიდანი'], 'kind' => 'volume',
            'attribute_codes' => ['tsqlis-avzis-motsuloba', 'motsuloba', 'tevadoba'],
            'attributes' => ['tsqlis-avzis-motsuloba', 'motsuloba', 'tevadoba'],
        ],
        'tablets' => [
            'categories' => ['პლანშეტები', 'საბავშვო ტაბი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'rom_storage',
            'attribute_codes' => ['shida-motsuloba', 'shida-mekhsiereba', 'flesh-mekhsiereba'],
            'attributes' => ['shida-motsuloba', 'shida-mekhsiereba', 'flesh-mekhsiereba'],
            'caption' => [
                'ka' => 'შიდა მეხსიერება',
            ],
        ],
        'monitors' => [
            'categories' => ['მონიტორი', 'გეიმინგ მონიტორი', 'ინტერაქტიული ეკრანი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'screen_inch',
            'attribute_codes' => ['ekranis-zoma', 'ekranis-diagonali', 'screen'],
            'attributes' => ['ekranis-zoma', 'ekranis-diagonali', 'screen'],
            'caption' => [
                'ka' => 'ეკრანის დიაგონალი',
            ],
        ],
        'desktop_ssd' => [
            'categories' => ['პანელური / All-in-One კომპიუტერები', 'სისტემური ბლოკი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'ssd_storage',
            'attribute_codes' => ['ssd-motsuloba'],
            'attributes' => ['ssd-motsuloba'],
            'caption' => [
                'ka' => 'SSD მოცულობა',
            ],
        ],
        'storage_media' => [
            'categories' => ['მეხსიერება SSD/HDD', 'გარე მყარი დისკები', 'USB ფლეშ მეხსიერება', 'Micro SD ბარათი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'storage',
            'attribute_codes' => ['shida-mekhsiereba', 'motsuloba', 'mekhsierebis-tevadoba'],
            'attributes' => ['shida-mekhsiereba', 'motsuloba', 'mekhsierebis-tevadoba'],
            'caption' => [
                'ka' => 'მეხსიერების მოცულობა',
            ],
        ],
        'watch_diagonal' => [
            'categories' => ['სმარტ საათები', 'საბავშვო ჭკვიანი საათი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'screen_inch',
            'attribute_codes' => ['ekranis-zoma', 'screen'],
            'attributes' => ['ekranis-zoma', 'screen'],
            'caption' => [
                'ka' => 'ეკრანის დიაგონალი',
            ],
        ],
        'battery_capacity' => [
            'categories' => ['პორტატული დამტენი/ფაუერ ბანკი', 'მანქანის აკუმულატორისთვის'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'charge',
            'attribute_codes' => ['elementis-motsuloba', 'akumulatoris-tevadoba', 'elementi', 'motsuloba-mah'],
            'attributes' => ['elementis-motsuloba', 'akumulatoris-tevadoba', 'elementi', 'motsuloba-mah'],
            'caption' => [
                'ka' => 'ბატარეის ტევადობა',
            ],
        ],
        'charger_output' => [
            'categories' => ['ნოუთბუქის დამტენი', 'დამტენი ადაპტერი', 'უსადენო დამტენი', 'მანქანის დამტენი', 'მანქანის დამტენები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre', 'maksimaluri-simdzlavre', 'maksimaluri-saerto-simdzlavre', 'gamomavali-simdzlavre', 'saerto-simdzlavre'],
            'attributes' => ['simdzlavre', 'maksimaluri-simdzlavre', 'maksimaluri-saerto-simdzlavre', 'gamomavali-simdzlavre', 'saerto-simdzlavre'],
            'caption' => [
                'ka' => 'გამომავალი სიმძლავრე',
            ],
        ],
        'fitted_accessories' => [
            'categories' => ['მობილური ტელეფონის აქსესუარები', 'პლანშეტის აქსესუარები', 'მონიტორის აქსესუარები', 'პლანშეტის ეკრანის დამცავი', 'დამცავი ქეისი', 'ეკრანის დამცავი', 'ტელევიზორის აქსესუარები', 'პროექტორის აქსესუარები', 'ყავის აპარატის აქსესუარები', 'ციფრული კამერის აქსესუარები', 'PlayStation-ის აქსესუარები', 'Xbox-ის აქსესუარები', 'მტვერსასრუტის აქსესუარები', 'ყურსასმენის აქსესუარები', 'გათბობის აქსესუარები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'compatibility_model',
            'attribute_codes' => ['tavsebadoba', 'tavsebadi-motsqobilobebi', 'tavsebadi-motsqobiloba', 'damzadebulia-modelistvis', 'tsarmoebulia-modelistvis'],
            'attributes' => ['tavsebadoba', 'tavsebadi-motsqobilobebi', 'tavsebadi-motsqobiloba', 'damzadebulia-modelistvis', 'tsarmoebulia-modelistvis'],
            'caption' => [
                'ka' => 'თავსებადი მოდელი',
            ],
        ],
        'watch_straps' => [
            'categories' => ['სმარტ საათის აქსესუარები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'strap_size',
            'attribute_codes' => ['samajuris-tavsebadoba'],
            'attributes' => ['samajuris-tavsebadoba'],
            'caption' => [
                'ka' => 'სამაჯურის თავსებადობა',
            ],
        ],
        'laptop_accessory_fit' => [
            'categories' => ['ნოუთბუქის აქსესუარები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'laptop_fit',
            'attribute_codes' => ['tavsebadoba', 'tavsebadi-motsqobilobebi', 'tavsebadi-motsqobiloba', 'chantis-zoma', 'leptopis-ekranis-maksimaluri-zoma'],
            'attributes' => ['tavsebadoba', 'tavsebadi-motsqobilobebi', 'tavsebadi-motsqobiloba', 'chantis-zoma', 'leptopis-ekranis-maksimaluri-zoma', 'leptopis-maksimaluri-zoma'],
            'caption' => [
                'ka' => 'თავსებადობა / ლეპტოპის ზომა',
            ],
        ],
        'laptop_bags' => [
            'categories' => ['ნოუთბუქის ჩანთა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'compatible_screen',
            'attribute_codes' => ['zoma', 'chantis-zoma', 'ekranis-zomastan-tavsebadoba'],
            'attributes' => ['zoma', 'chantis-zoma', 'ekranis-zomastan-tavsebadoba'],
            'caption' => [
                'ka' => 'თავსებადი ეკრანის ზომა',
            ],
        ],
        'game_platform' => [
            'categories' => ['თამაშები', 'PlayStation-ის თამაშები', 'კონტროლერები', 'Nintendo Switch', 'PlayStation', 'სათამაშო კონსოლები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'platform',
            'attribute_codes' => ['konsoltan-tavsebadoba', 'story-mode'],
            'attributes' => ['konsoltan-tavsebadoba', 'story-mode'],
            'caption' => [
                'ka' => 'თავსებადი პლატფორმა',
            ],
        ],
        'router_standard' => [
            'categories' => ['როუტერი', 'ქსელური მოწყობილობები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'wifi_standard',
            'attribute_codes' => ['wifi-standartebi', 'wi-fi-standarti', 'ukabelo-tsvdomis-standarti'],
            'attributes' => ['wifi-standartebi', 'wi-fi-standarti', 'ukabelo-tsvdomis-standarti'],
            'caption' => [
                'ka' => 'Wi-Fi სტანდარტი',
            ],
            'requires_in_categories' => [
                'ქსელური მოწყობილობები' => [
                    'code' => 'tipi',
                    'values' => ['Wi-Fi როუტერი', 'Access Point', 'Wi-Fi Router'],
                ],
            ],
        ],
        'mouse_sensitivity' => [
            'categories' => ['მაუსი', 'გეიმინგ მაუსი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'dpi',
            'attribute_codes' => ['dpi', 'dpi-mgrdznobeloba'],
            'attributes' => ['dpi', 'dpi-mgrdznobeloba'],
            'caption' => [
                'ka' => 'DPI მგრძნობელობა',
            ],
        ],
        'keyboard_switch' => [
            'categories' => ['კლავიატურა', 'გეიმინგ კლავიატურა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'switch_type',
            'attribute_codes' => ['gadamrtvelebi', 'gadamrtvelis-tipi', 'gadamrtveli', 'mekanikuri-gadamrtvelebis-tipi'],
            'attributes' => ['gadamrtvelebi', 'gadamrtvelis-tipi', 'gadamrtveli', 'mekanikuri-gadamrtvelebis-tipi'],
            'caption' => [
                'ka' => 'გადამრთველის ტიპი',
            ],
        ],
        'print_technology' => [
            'categories' => ['პრინტერი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'printing_technology',
            'attribute_codes' => ['teknologia', 'bechdvis-teknologia'],
            'attributes' => ['teknologia', 'bechdvis-teknologia'],
            'caption' => [
                'ka' => 'ბეჭდვის ტექნოლოგია',
            ],
        ],
        'printer_compatibility' => [
            'categories' => ['კარტრიჯები და ტონერები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'compatibility_model',
            'attribute_codes' => ['tavsebadoba', 'printertan-tavsebadoba', 'tavsebadi-modelebi'],
            'attributes' => ['tavsebadoba', 'printertan-tavsebadoba', 'tavsebadi-modelebi'],
            'caption' => [
                'ka' => 'თავსებადი პრინტერი',
            ],
        ],
        'projector_native_resolution' => [
            'categories' => ['პროექტორი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'native_resolution',
            'attribute_codes' => ['mshobliuri-garchevadoba', 'mshobliuri-rezolutsia', 'dziritadi-garchevadoba', 'mshobliuri-native-rezolutsia', 'naturaluri-garchevadoba'],
            'attributes' => ['mshobliuri-garchevadoba', 'mshobliuri-rezolutsia', 'dziritadi-garchevadoba', 'mshobliuri-native-rezolutsia', 'naturaluri-garchevadoba'],
            'caption' => [
                'ka' => 'მშობლიური გარჩევადობა',
            ],
        ],
        'audio_output' => [
            'categories' => ['კომპიუტერის დინამიკი', 'პორტატული დინამიკი', 'საუნდბარი', 'Party დინამიკი', 'PC დინამიკები', 'სახლის კინოთეატრი და საუნდბარი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'audio_power',
            'attribute_codes' => ['saerto-gamomavali-simdzlavre-rms', 'jamuri-simdzlavre-rms', 'simdzlavre', 'gamomavali-simdzlavre', 'saerto-gamomavali-simdzlavre', 'spikeris-simdzlavre', 'audio-gamomushavebuli-simdzlavre-vt'],
            'attributes' => ['saerto-gamomavali-simdzlavre-rms', 'jamuri-simdzlavre-rms', 'simdzlavre', 'gamomavali-simdzlavre', 'saerto-gamomavali-simdzlavre', 'spikeris-simdzlavre', 'audio-gamomushavebuli-simdzlavre-vt'],
            'caption' => [
                'ka' => 'ხმის სიმძლავრე',
            ],
            'unit_from_name_codes' => ['audio-gamomushavebuli-simdzlavre-vt'],
        ],
        'headphone_runtime' => [
            'categories' => ['აუდიო სისტემები', 'ყურსასმენები', 'უსადენო ყურსასმენები', 'საბავშვო ყურსასმენი', 'Buds', 'გეიმინგ ყურსასმენები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'runtime',
            'attribute_codes' => ['mushaobis-dro', 'elementis-khangrdzlivoba', 'elementis-mushaobis-khangrdzlivoba'],
            'attributes' => ['mushaobis-dro', 'elementis-khangrdzlivoba', 'elementis-mushaobis-khangrdzlivoba'],
            'caption' => [
                'ka' => 'მუშაობის ხანგრძლივობა',
            ],
        ],
        'wired_headphone_driver' => [
            'categories' => ['კომპიუტერის ყურსასმენი', 'სადენიანი ყურსასმენები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'diameter',
            'attribute_codes' => ['dinamikis-diametri'],
            'attributes' => ['dinamikis-diametri'],
            'caption' => [
                'ka' => 'დინამიკის დიამეტრი',
            ],
        ],
        'camera_photo_resolution' => [
            'categories' => ['ციფრული კამერა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'megapixels',
            'attribute_codes' => ['foto-rezolutsia'],
            'attributes' => ['foto-rezolutsia'],
            'caption' => [
                'ka' => 'ფოტო რეზოლუცია',
            ],
        ],
        'action_video_resolution' => [
            'categories' => ['ექშენ კამერა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'video_resolution',
            'attribute_codes' => ['max-video-rezolutsia'],
            'attributes' => ['max-video-rezolutsia'],
            'caption' => [
                'ka' => 'მაქსიმალური ვიდეო რეზოლუცია',
            ],
        ],
        'security_video_resolution' => [
            'categories' => ['ვიდეო რეგისტრატორი', 'ჭკვიანი კამერა', 'უსაფრთხოების კამერები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'video_resolution',
            'attribute_codes' => ['video-rezolutsia', 'tsina-kameris-rezolutsia', 'kameris-garchevadoba', 'maksimaluri-videos-rezolutsia', 'videos-chatseris-garchevadoba'],
            'attributes' => ['video-rezolutsia', 'tsina-kameris-rezolutsia', 'kameris-garchevadoba', 'maksimaluri-videos-rezolutsia', 'videos-chatseris-garchevadoba'],
            'caption' => [
                'ka' => 'ვიდეო რეზოლუცია',
            ],
        ],
        'drone_flight_time' => [
            'categories' => ['დრონი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'flight_time',
            'attribute_codes' => ['frenis-dro', 'maksimaluri-frenis-dro', 'frenis-maksimaluri-dro'],
            'attributes' => ['frenis-dro', 'maksimaluri-frenis-dro', 'frenis-maksimaluri-dro'],
            'caption' => [
                'ka' => 'მაქსიმალური ფრენის დრო',
            ],
        ],
        'electrical_appliance_tool_power' => [
            'categories' => ['სამზარეულოს წვრილი ტექნიკა', 'ბლენდერი და ჩოპერი', 'პურის საცხობი', 'მიქსერი', 'ტოსტერი', 'გრილი', 'სენდვიჩ მეიქერი', 'ვაფლის მეიქერი', 'სლაშ მეიქერი', 'სამზარეულოს კომბაინი', 'ხორცსაკეპი', 'თმის უთო', 'თმის ფენი', 'თმის სახვევი', 'ეპილატორი', 'ყავის საფქვავი', 'რძის ამომყვანი', 'ყინულის აპარატი', 'ორთქლის უთო', 'ხელსაწყოები', 'ელექტრო ხერხები და ბეწვა ხერხები', 'დისკური ხერხი', 'ელექტრო ბურღი', 'ელექტრო ზუმფარა და სახეხი მანქანა', 'ელექტრო სამშენებლო მიქსერი', 'ელექტრო სალესები', 'ტექნიკური ფენი', 'კუთხსახეხი', 'ბორმანქანა - მრავალფუნქციური ხელსაწყო', 'პერფორატორი', 'საპრიალებელი მანქანა - პოლირების აპარატი', 'შალაშინები', 'საბურღი დანადგარები', 'საჭრელი დანადგარები', 'ტორსული ხერხები', 'მარმარილოს საჭრელი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre', 'nominaluri-simdzlavre', 'energia-mokhmareba-simdzlavre-vt', 'energia-mokhmareba-simdzlavris-diapazoni-vt', 'dzala-simdzlavre', 'sheqvanis-nominaluri-simdzlavre'],
            'attributes' => ['simdzlavre', 'nominaluri-simdzlavre', 'energia-mokhmareba-simdzlavre-vt', 'energia-mokhmareba-simdzlavris-diapazoni-vt', 'dzala-simdzlavre', 'sheqvanis-nominaluri-simdzlavre'],
            'caption' => [
                'ka' => 'სიმძლავრე',
            ],
            'unit_from_name_codes' => ['energia-mokhmareba-simdzlavre-vt', 'energia-mokhmareba-simdzlavris-diapazoni-vt'],
            'requires_in_categories' => [
                'სამზარეულოს წვრილი ტექნიკა' => [
                    'code' => 'tipi',
                    'values' => ['ჩოპერი', 'გრილი', 'Chopper', 'Grill'],
                ],
                'ხელსაწყოები' => [
                    'code' => 'tipi',
                    'values' => ['დარტყმითი ბურღი', 'ელექტრო სალესი', 'აკუმულატორის დამტენი', 'Impact drill', 'Electric grinder', 'Battery charger'],
                ],
            ],
        ],
        'shaver_runtime' => [
            'categories' => ['ტრიმერი', 'წვერსაპარსი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'runtime',
            'attribute_codes' => ['mushaobis-dro', 'mushaobis-khangrdzlivoba', 'mushaobis-maksimaluri-khangrdzlivoba', 'battery-batareis-khangrdzlivoba-tst', 'battery-batareis-khangrdzlivoba-st'],
            'attributes' => ['mushaobis-dro', 'mushaobis-khangrdzlivoba', 'mushaobis-maksimaluri-khangrdzlivoba', 'battery-batareis-khangrdzlivoba-tst', 'battery-batareis-khangrdzlivoba-st'],
            'caption' => [
                'ka' => 'მუშაობის ხანგრძლივობა',
            ],
            'unit_from_name_codes' => ['battery-batareis-khangrdzlivoba-tst', 'battery-batareis-khangrdzlivoba-st'],
        ],
        'iron_steam_rate' => [
            'categories' => ['უთო'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'steam_rate',
            'attribute_codes' => ['ortklis-mitsodeba', 'uncategorized-ortklis-gamomushaveba-gtst'],
            'attributes' => ['ortklis-mitsodeba', 'uncategorized-ortklis-gamomushaveba-gtst'],
            'caption' => [
                'ka' => 'ორთქლის მიწოდება',
            ],
            'unit_from_name_codes' => ['uncategorized-ortklis-gamomushaveba-gtst'],
        ],
        'coffee_pump_pressure' => [
            'categories' => ['ყავის აპარატები და აქსესუარები', 'ყავის აპარატი', 'ყავის მადუღარა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'pressure',
            'attribute_codes' => ['tumbos-tsneva', 'tsneva', 'uncategorized-tumbos-tsneva-bari'],
            'attributes' => ['tumbos-tsneva', 'tsneva', 'uncategorized-tumbos-tsneva-bari'],
            'caption' => [
                'ka' => 'ტუმბოს წნევა',
            ],
            'unit_from_name_codes' => ['uncategorized-tumbos-tsneva-bari'],
            'requires_in_categories' => [
                'ყავის აპარატები და აქსესუარები' => [
                    'code' => 'tipi',
                    'values' => ['ყავის აპარატი', 'Coffee machine'],
                ],
            ],
        ],
        'vacuum_suction' => [
            'categories' => ['მტვერსასრუტი', 'რობოტი მტვერსასრუტი', 'მტვერსასრუტები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'suction',
            'attribute_codes' => ['shesrutvis-simdzlavre', 'shetsovis-simdzlavre', 'dzlieri-shetsovis-simdzlavre', 'mtversasrutis-makhasiateblebi-shetsovis-simdzlavre-vt', 'mtversasrutis-makhasiateblebi-shetsovis-simdzlavre-pa'],
            'attributes' => ['shesrutvis-simdzlavre', 'shetsovis-simdzlavre', 'dzlieri-shetsovis-simdzlavre', 'mtversasrutis-makhasiateblebi-shetsovis-simdzlavre-vt', 'mtversasrutis-makhasiateblebi-shetsovis-simdzlavre-pa'],
            'caption' => [
                'ka' => 'შეწოვის სიმძლავრე',
            ],
            'unit_from_name_codes' => ['mtversasrutis-makhasiateblebi-shetsovis-simdzlavre-vt', 'mtversasrutis-makhasiateblebi-shetsovis-simdzlavre-pa'],
        ],
        'cooker_cavity' => [
            'categories' => ['ჩასაშენებელი ტექნიკა'],
            'legacy_main_categories' => [87],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'cavity_volume',
            'attribute_codes' => ['shida-motsuloba', 'ghumelis-motsuloba', 'gazkuris-motsuloba'],
            'attributes' => ['shida-motsuloba', 'ghumelis-motsuloba', 'gazkuris-motsuloba'],
            'caption' => [
                'ka' => 'ღუმელის მოცულობა',
            ],
            'requires_in_categories' => [
                'ჩასაშენებელი ტექნიკა' => [
                    'code' => 'tipi',
                    'values' => ['ღუმელი', 'გაზქურა', 'Oven', 'Cooker'],
                ],
            ],
        ],
        'total_burners' => [
            'categories' => ['გაზქურა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'burner_count',
            'attribute_codes' => ['komforebis-raodenoba', 'kanforebis-raodenoba'],
            'attributes' => ['komforebis-raodenoba', 'kanforebis-raodenoba'],
            'caption' => [
                'ka' => 'კონფორების რაოდენობა',
                'en' => 'Burner count',
            ],
        ],
        'multi_cooker_capacity' => [
            'categories' => ['მულტი სახარში'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre'],
            'attributes' => ['simdzlavre'],
            'caption' => [
                'ka' => 'სიმძლავრე',
            ],
            'unit_from_name_ids' => [],
        ],
        'cookware_capacity' => [
            'categories' => ['ქვაბი', 'ჩაიდნები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'fluid_capacity',
            'attribute_codes' => ['motsuloba'],
            'attributes' => ['motsuloba'],
            'caption' => [
                'ka' => 'მოცულობა',
            ],
        ],
        'pan_diameter' => [
            'categories' => ['ტაფა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'cookware_diameter',
            'attribute_codes' => ['diametri'],
            'attributes' => ['diametri'],
            'caption' => [
                'ka' => 'დიამეტრი',
            ],
        ],
        'bottle_capacity' => [
            'categories' => ['ბოთლები', 'სამზარეულოს ჭურჭელი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'fluid_capacity',
            'attribute_codes' => ['tevadoba'],
            'attributes' => ['tevadoba'],
            'caption' => [
                'ka' => 'მოცულობა',
            ],
        ],
        'water_pump_pressure' => [
            'categories' => ['წყლის ტუმბო'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'pressure',
            'attribute_codes' => ['samushao-tsneva'],
            'attributes' => ['samushao-tsneva'],
            'caption' => [
                'ka' => 'სამუშაო წნევა',
            ],
        ],
        'driver_torque' => [
            'categories' => ['ხრახნდამჭერი - ელექტრო სახრახნისი', 'ქანჩები და თავაკები ავტომობილისთვის'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'torque',
            'attribute_codes' => ['maksimaluri-brunvis-momenti'],
            'attributes' => ['maksimaluri-brunvis-momenti'],
            'caption' => [
                'ka' => 'მაქსიმალური ბრუნვის მომენტი',
            ],
        ],
        'generator_output' => [
            'categories' => ['გენერატორი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre'],
            'attributes' => ['simdzlavre'],
            'caption' => [
                'ka' => 'გამომავალი სიმძლავრე',
            ],
        ],
        'welder_current' => [
            'categories' => ['შედუღების აპარატი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'current',
            'attribute_codes' => ['simdzlavris-diapazoni'],
            'attributes' => ['simdzlavris-diapazoni'],
            'caption' => [
                'ka' => 'შედუღების დენის დიაპაზონი',
            ],
        ],
        'transport_motor_power' => [
            'categories' => ['ელექტრო ტრანსპორტი', 'ელექტრო სკუტერი', 'ელექტრო ველოსიპედი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre', 'energia-mokhmareba-simdzlavre-vt'],
            'attributes' => ['simdzlavre', 'energia-mokhmareba-simdzlavre-vt'],
            'caption' => [
                'ka' => 'ძრავის სიმძლავრე',
            ],
            'unit_from_name_codes' => ['energia-mokhmareba-simdzlavre-vt'],
        ],
        'children_vehicle_voltage' => [
            'categories' => ['საბავშვო მანქანები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'battery_voltage',
            'attribute_codes' => ['akumulatoris-motsuloba', 'samushao-dzabva', 'akumulatori'],
            'attributes' => ['akumulatoris-motsuloba', 'samushao-dzabva', 'akumulatori'],
            'caption' => [
                'ka' => 'ბატარეის ძაბვა',
            ],
        ],
        'cable_length' => [
            'categories' => ['ქსელის კაბელები', 'კაბელები და ადაპტერები', 'USB კაბელი', 'კაბელები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'cable_length',
            'attribute_codes' => ['sigrdze', 'kabelis-sigrdze'],
            'attributes' => ['sigrdze', 'kabelis-sigrdze'],
            'caption' => [
                'ka' => 'კაბელის სიგრძე',
            ],
        ],
        'wall_mount_screen' => [
            'categories' => ['კედლის საკიდი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'compatible_screen',
            'attribute_codes' => ['zoma', 'tavsebadi-televizoris-ekranis-zoma', 'maksimaluri-tavsebadi-zoma', 'tavsebadi-zoma', 'tavsebadi-ekranis-zoma'],
            'attributes' => ['zoma', 'tavsebadi-televizoris-ekranis-zoma', 'maksimaluri-tavsebadi-zoma', 'tavsebadi-zoma', 'tavsebadi-ekranis-zoma'],
            'caption' => [
                'ka' => 'თავსებადი ეკრანის ზომა',
            ],
        ],
        'pc_supply_power' => [
            'categories' => ['კვების წყაროები', 'კვების ბლოკი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre'],
            'attributes' => ['simdzlavre'],
            'caption' => [
                'ka' => 'სიმძლავრე',
            ],
        ],
        'webcam_resolution' => [
            'categories' => ['კომპიუტერის ვებკამერა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'video_resolution',
            'attribute_codes' => ['rezolutsia'],
            'attributes' => ['rezolutsia'],
            'caption' => [
                'ka' => 'ვიდეო რეზოლუცია',
            ],
        ],
        'microphone_pickup' => [
            'categories' => ['გეიმინგ მიკროფონი', 'საბავშვო მიკროფონი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'pickup_pattern',
            'attribute_codes' => ['khmis-mighebis-tipi'],
            'attributes' => ['khmis-mighebis-tipi'],
            'caption' => [
                'ka' => 'ხმის მიღების ტიპი',
            ],
        ],
        'air_compressor_tank' => [
            'categories' => ['ჰაერის კომპრესორი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'fluid_capacity',
            'attribute_codes' => ['motsuloba', 'avzis-motsuloba'],
            'attributes' => ['motsuloba', 'avzis-motsuloba'],
            'caption' => [
                'ka' => 'ავზის მოცულობა',
            ],
        ],
        'washer_pressure' => [
            'categories' => ['მაღალი წნევით სარეცხი დანადგარები', 'მაღალი წნევით მრეცხავი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'pressure',
            'attribute_codes' => ['tsneva'],
            'attributes' => ['tsneva'],
            'caption' => [
                'ka' => 'სამუშაო წნევა',
            ],
        ],
        'lift_capacity' => [
            'categories' => ['ჰიდრავლიკური ამწეები', 'ტვირთმზიდი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'rated_load',
            'attribute_codes' => ['maksimaluri-datvirtva', 'maksimaluri-tsona', 'maks-tsona'],
            'attributes' => ['maksimaluri-datvirtva', 'maksimaluri-tsona', 'maks-tsona'],
            'caption' => [
                'ka' => 'დასაშვები დატვირთვა',
            ],
        ],
        'smart_switch_count' => [
            'categories' => ['ჭკვიანი ჩამრთველები', 'სმარტ ჩამრთველები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'switch_count',
            'attribute_codes' => ['chamrtvelebis-raodenoba'],
            'attributes' => ['chamrtvelebis-raodenoba'],
            'caption' => [
                'ka' => 'ჩამრთველების რაოდენობა',
            ],
        ],
        'cutlery_set_size' => [
            'categories' => ['დანა-ჩანგლის ნაკრები', 'სამზარეულოს დანები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'piece_count',
            'attribute_codes' => ['nivtebis-raodenoba'],
            'attributes' => ['nivtebis-raodenoba'],
            'caption' => [
                'ka' => 'ნაკრებში რაოდენობა',
            ],
        ],
        'coffee_package_mass' => [
            'categories' => ['ყავა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'product_mass',
            'attribute_codes' => ['tsona'],
            'attributes' => ['tsona'],
            'caption' => [
                'ka' => 'შეფუთვის წონა',
            ],
        ],
        'fan_power' => [
            'categories' => ['ვენტილატორები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre'],
            'attributes' => ['simdzlavre'],
            'caption' => [
                'ka' => 'სიმძლავრე',
            ],
        ],
        'lighting_power' => [
            'categories' => ['ჭკვიანი განათება'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_codes' => ['simdzlavre'],
            'attributes' => ['simdzlavre'],
            'caption' => [
                'ka' => 'სიმძლავრე',
            ],
        ],
        'chair_user_capacity' => [
            'categories' => ['გეიმინგ სავარძელი', 'საოფისე სავარძელი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'rated_load',
            'attribute_codes' => ['maksimaluri-datvirtva', 'maksimaluri-simdzime', 'momkhmareblis-maksimaluri-tsona'],
            'attributes' => ['maksimaluri-datvirtva', 'maksimaluri-simdzime', 'momkhmareblis-maksimaluri-tsona'],
            'caption' => [
                'ka' => 'მომხმარებლის მაქსიმალური წონა',
            ],
        ],
        'scale_capacity' => [
            'categories' => ['სამზარეულოს სასწორი', 'სასწორი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'rated_load',
            'attribute_codes' => ['maksimaluri-datvirtva', 'maksimaluri-tsona'],
            'attributes' => ['maksimaluri-datvirtva', 'maksimaluri-tsona'],
            'caption' => [
                'ka' => 'მაქსიმალური ასაწონი წონა',
            ],
        ],
        'dispenser_cold_tank' => [
            'categories' => ['წყლის დისპენსერი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'fluid_capacity',
            'attribute_codes' => ['tsivi-tsqlis-rezervuaris-motsuloba'],
            'attributes' => ['tsivi-tsqlis-rezervuaris-motsuloba'],
            'caption' => [
                'ka' => 'ცივი წყლის ავზის მოცულობა',
            ],
        ],
        'furniture_dimensions' => [
            'categories' => ['თარო', 'კარადა', 'საკიდი', 'ორადგილიანი საწოლი', 'ტელევიზორის მაგიდა', 'ლეპტოპის მაგიდა', 'მატრასები', 'ყავის მაგიდა', 'მაგიდის და სკამის კომპლექტი', 'საოფისე/სამეცადინო მაგიდები', 'სკამები და სავარძლები', 'მაგიდა', 'ორსართულიანი საწოლი', 'ორგანაიზერი', 'საოფისე სავარძელი', 'სარკეები', 'ტუმბო', 'საბავშვო ავეჯი', 'ფეხსაცმლის კარადა'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'furniture_dimensions',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'პროდუქტის სრული ზომები',
            ],
        ],
        'lamp_height' => [
            'categories' => ['ტორშერი', 'მაგიდის სანათი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'height',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'სიმაღლე',
            ],
        ],
        'wood_router_power' => [
            'categories' => ['ფრეზერი - ელექტრო როუტერი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'სიმძლავრე',
            ],
        ],
        'machine_named_input_power' => [
            'categories' => ['დაზგები და მანქანები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'power',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'შემავალი სიმძლავრე',
            ],
        ],
        'disc_working_diameter' => [
            'categories' => ['საჭრელი დისკები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'diameter',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'დისკის დიამეტრი',
            ],
        ],
        'drill_working_diameter' => [
            'categories' => ['საბურღი პირები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'diameter',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'ბურღის დიამეტრი',
            ],
        ],
        'spray_nozzle' => [
            'categories' => ['ელექტრო პულვერიზატორი - სამღებრო აპარატი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'diameter',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'საქშენის ზომა',
            ],
        ],
        'garden_hose_length' => [
            'categories' => ['სარწყავი მილი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'cable_length',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'მილის სიგრძე',
            ],
        ],
        'children_recommended_age' => [
            'categories' => ['კარტინგები', 'საბავშვო ავეჯი', 'ველოსიპედი', 'მოტოციკლები'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'recommended_age',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'რეკომენდებული ასაკი',
            ],
        ],
        'press_force' => [
            'categories' => ['ჰიდრავლიკური პრესი'],
            'inherit' => false,
            'strict_codes' => true,
            'kind' => 'press_force',
            'attribute_ids' => [],
            'attributes' => [],
            'caption' => [
                'ka' => 'წნევის ძალა',
            ],
        ],
    ],
    'description_rules' => [
        'televisions' => [
            'headers' => ['ეკრანის დიაგონალი', 'ეკრანის ზომა', 'Screen diagonal', 'Screen size'],
        ],
        'phones' => [
            'headers' => ['შიდა მეხსიერება', 'ჩაშენებული მეხსიერება', 'Internal storage', 'Storage capacity'],
        ],
        'refrigerators' => [
            'headers' => ['მთლიანი მოცულობა', 'საერთო მოცულობა', 'ჯამური მოცულობა', 'Total volume', 'Total capacity'],
        ],
        'washing_machines' => [
            'headers' => ['მაქსიმალური ჩატვირთვა', 'რეცხვის მოცულობა', 'რეცხვის ტევადობა', 'Washing capacity'],
        ],
        'christmas_trees' => [
            'headers' => ['ნაძვის სიმაღლე', 'სიმაღლე', 'Tree height', 'Height'],
        ],
        'climate_equipment' => [
            'headers' => ['რეკომენდირებული ფართი', 'რეკომენდებული ფართი', 'გათბობის ფართობი', 'გასათბობი ფართობი', 'Recommended area', 'Heating area'],
        ],
        'charger_output' => [
            'headers' => ['მაქსიმალური გამომავალი სიმძლავრე', 'საერთო გამომავალი სიმძლავრე', 'გამომავალი სიმძლავრე', 'დატენვის სიმძლავრე', 'სიმძლავრე', 'Output power', 'Maximum output power'],
        ],
        'fitted_accessories' => [
            'headers' => ['თავსებადი მოდელები', 'დამზადებულია მოდელისთვის', 'თავსებადი მოწყობილობები', 'Compatible models'],
        ],
        'laptop_accessory_fit' => [
            'headers' => ['ლეპტოპის ეკრანის მაქსიმალური ზომა', 'ლეპტოპის მაქსიმალური ზომა', 'თავსებადი ლეპტოპის ზომა', 'თავსებადი მოდელები', 'Compatible laptop size', 'Compatible models'],
        ],
        'laptop_bags' => [
            'headers' => ['ჩანთის ზომა', 'თავსებადი ეკრანის ზომა', 'ეკრანის ზომასთან თავსებადობა', 'Compatible screen size'],
        ],
        'printer_compatibility' => [
            'headers' => ['თავსებადი მოდელები', 'დამზადებულია მოდელისთვის', 'თავსებადი მოწყობილობები', 'Compatible models'],
        ],
        'headphone_runtime' => [
            'headers' => ['მუშაობის ხანგრძლივობა', 'ელემენტის ხანგრძლივობა', 'მუშაობის დრო', 'Operating time', 'Battery life'],
        ],
        'wired_headphone_driver' => [
            'headers' => ['დიამეტრი', 'Diameter'],
        ],
        'electrical_appliance_tool_power' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'shaver_runtime' => [
            'headers' => ['მუშაობის ხანგრძლივობა', 'ელემენტის ხანგრძლივობა', 'მუშაობის დრო', 'Operating time', 'Battery life'],
        ],
        'iron_steam_rate' => [
            'headers' => ['ორთქლის მიწოდება', 'ორთქლის გამომუშავება', 'Steam output'],
        ],
        'coffee_pump_pressure' => [
            'headers' => ['ტუმბოს წნევა', 'სამუშაო წნევა', 'Pump pressure', 'Working pressure'],
        ],
        'vacuum_suction' => [
            'headers' => ['შეწოვის სიმძლავრე', 'შესრუტვის სიმძლავრე', 'Suction power', 'Suction pressure'],
        ],
        'multi_cooker_capacity' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'water_pump_pressure' => [
            'headers' => ['ტუმბოს წნევა', 'სამუშაო წნევა', 'Pump pressure', 'Working pressure'],
        ],
        'generator_output' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'transport_motor_power' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'children_vehicle_voltage' => [
            'headers' => ['აკუმულატორის ძაბვა', 'ბატარეის ძაბვა', 'აკუმულატორი', 'ბატარეა', 'Battery voltage', 'Battery'],
        ],
        'cable_length' => [
            'headers' => ['კაბელის სიგრძე', 'Cable length'],
        ],
        'wall_mount_screen' => [
            'headers' => ['ჩანთის ზომა', 'თავსებადი ეკრანის ზომა', 'ეკრანის ზომასთან თავსებადობა', 'Compatible screen size'],
        ],
        'pc_supply_power' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'washer_pressure' => [
            'headers' => ['ტუმბოს წნევა', 'სამუშაო წნევა', 'Pump pressure', 'Working pressure'],
        ],
        'fan_power' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'lighting_power' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'furniture_dimensions' => [
            'headers' => [],
        ],
        'lamp_height' => [
            'headers' => ['სიმაღლე', 'Height'],
        ],
        'wood_router_power' => [
            'headers' => ['შემავალი სიმძლავრე', 'ნომინალური სიმძლავრე', 'სიმძლავრე', 'Input power', 'Rated power', 'Power'],
        ],
        'machine_named_input_power' => [
            'headers' => ['შემავალი სიმძლავრე', 'Input power'],
        ],
        'disc_working_diameter' => [
            'headers' => ['დისკის დიამეტრი', 'საჭრელი დისკის დიამეტრი', 'Disc diameter'],
        ],
        'drill_working_diameter' => [
            'headers' => ['ბურღის დიამეტრი', 'დიამეტრი', 'Drill diameter'],
        ],
        'spray_nozzle' => [
            'headers' => ['საქშენის ზომა', 'საქშენის დიამეტრი', 'საქშენი', 'Nozzle diameter', 'Nozzle size'],
        ],
        'garden_hose_length' => [
            'headers' => ['შლანგის სიგრძე', 'სიგრძე', 'Hose length', 'Length'],
        ],
        'children_recommended_age' => [
            'headers' => ['რეკომენდებული ასაკი', 'ასაკობრივი ჯგუფი', 'ასაკი', 'Recommended age', 'Age'],
        ],
        'press_force' => [
            'headers' => ['წნევის ძალა', 'Press force'],
        ],
    ],
];
