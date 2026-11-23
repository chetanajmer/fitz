<?php

/*
|--------------------------------------------------------------------------
| Gymai default fitness library — diet charts & workout plans
|--------------------------------------------------------------------------
| Imported per gym from Diet Charts / Workout Plans → "Import default library".
| Every category and plan has a stable `key`: never change a key once shipped,
| otherwise gyms that already imported it will get a duplicate. Add new items
| with new keys and bump `version`.
|
| Sections are [title, subtitle, body].
|
| Evidence base (general guidance, not medical advice):
| - ICMR-NIN Dietary Guidelines for Indians (2024) & "My Plate for the Day":
|   balanced Indian plate, food-first (no routine protein supplements), salt < 5 g/day.
| - ISSN position stand on protein & exercise (2017): 1.4–2.0 g/kg/day for exercisers,
|   spread over meals every 3–4 h.
| - ICMR-INDIAB (Diabetes Care, 2022): for diabetes, lower carbohydrate share
|   (~50–55%) with more protein (~18–20%); favour whole grains, millets, pulses.
| - PCOS: low-GI, high-fibre, adequate protein; weight loss improves outcomes.
| - ACSM resistance training update (2026) & 2009 position stand: ~80% 1RM / 2–3+ sets
|   for strength, ≥10 sets per muscle per week for hypertrophy, 8–12 reps for novices.
| - WHO (2020): 150–300 min moderate activity per week + muscle strengthening ≥2 days.
| Calories/protein per meal are approximate (~) — portion sizes vary; adjust per member.
*/

$dailyGuidelines = ['Daily guidelines', 'Apply to every day of this plan',
    "• Water: 2.5–3.5 litres a day (more on training days and in summer).\n• Cooking oil: 3–4 teaspoons in total for the whole day. Prefer mustard, groundnut, or rice-bran oil and rotate them.\n• Salt: under 1 teaspoon (5 g) a day, as ICMR advises. Go easy on pickles, papad and namkeen.\n• Sugar: avoid added sugar, sweets, packaged juices and soft drinks.\n• Sleep 7–8 hours. Walk 7,000–10,000 steps a day.\n• Swap foods within the same group (e.g. jowar roti for wheat roti, tofu for paneer) to suit your taste."];

$medicalNote = ['Important', 'Read before starting',
    "This is general guidance. Follow your doctor's advice on medication and blood tests, and have a registered dietitian personalise portions to your reports. Do not stop or change medicines on your own."];

return [
    'version' => '2026.12',

    // =====================================================================
    // DIET CHARTS
    // =====================================================================
    'diet' => ['categories' => [

        [
            'key' => 'diet-weight-loss',
            'name' => 'Weight Loss',
            'description' => 'Calorie-controlled Indian meal plans with high protein to lose fat while keeping muscle.',
            'plans' => [
                [
                    'key' => 'diet-wl-veg-1200',
                    'title' => '1200 kcal Vegetarian Fat Loss',
                    'summary' => 'Gentle calorie deficit for women and smaller-framed people. Simple home food, ~65 g protein. Pair it with strength training 3× a week.',
                    'level' => 'beginner', 'duration_weeks' => 8, 'daily_calories' => 1200,
                    'sections' => [
                        ['Early morning', '6:30 AM · ~60 kcal', "1 glass warm water\n5 soaked almonds + 2 walnut halves"],
                        ['Breakfast', '8:30 AM · ~280 kcal · 18 g protein', "2 medium moong dal chillas with vegetables\nMint-coriander chutney\n1 small bowl (100 g) low-fat curd"],
                        ['Mid-morning', '11:00 AM · ~80 kcal', '1 apple, guava or orange'],
                        ['Lunch', '1:30 PM · ~360 kcal · 15 g protein', "1 multigrain or jowar roti\n1 katori dal\n1 katori vegetable sabzi, cooked in little oil\nLarge plate of salad (cucumber, tomato, carrot)\n1 glass buttermilk"],
                        ['Evening snack', '5:00 PM · ~130 kcal · 7 g protein', "30 g roasted chana, or 1 katori sprouts chaat\nGreen tea or tea without sugar"],
                        ['Dinner', '8:00 PM · ~300 kcal · 20 g protein', "100 g low-fat paneer or tofu bhurji with capsicum and onion\n1 cup sautéed vegetables\n1 bowl clear vegetable soup"],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-wl-veg-1500',
                    'title' => '1500 kcal Vegetarian Fat Loss',
                    'summary' => 'Balanced vegetarian deficit plan for steady fat loss of about 0.5 kg a week, ~70 g protein.',
                    'level' => 'beginner', 'duration_weeks' => 12, 'daily_calories' => 1500,
                    'sections' => [
                        ['Early morning', '6:30 AM · ~60 kcal', "1 glass warm water\n5 soaked almonds"],
                        ['Breakfast', '8:30 AM · ~330 kcal · 14 g protein', "40 g oats cooked in 200 ml toned milk\n1 tablespoon mixed seeds (flax, chia or pumpkin)\nCinnamon for taste, no sugar"],
                        ['Mid-morning', '11:00 AM · ~120 kcal · 4 g protein', "1 seasonal fruit\n1 glass buttermilk"],
                        ['Lunch', '1:30 PM · ~460 kcal · 20 g protein', "2 multigrain rotis\n1 katori dal or rajma\n1 katori green vegetable sabzi\nSalad\n1 small bowl curd"],
                        ['Evening snack', '5:00 PM · ~150 kcal · 9 g protein', '1 katori moong sprouts chaat with onion, tomato and lemon'],
                        ['Dinner', '8:00 PM · ~340 kcal · 18 g protein', "75 g paneer tikka (grilled or air-fried)\n1 roti\n1 cup stir-fried vegetables"],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-wl-nonveg-1500',
                    'title' => '1500 kcal Non-Veg / Eggetarian Fat Loss',
                    'summary' => 'High-protein fat-loss plan with eggs, chicken and fish, ~100 g protein to protect muscle in a deficit.',
                    'level' => 'beginner', 'duration_weeks' => 12, 'daily_calories' => 1500,
                    'sections' => [
                        ['Early morning', '6:30 AM · ~40 kcal', "1 glass warm water\n5 soaked almonds"],
                        ['Breakfast', '8:30 AM · ~230 kcal · 20 g protein', "Omelette: 3 egg whites + 1 whole egg with onion, tomato and spinach\n1 slice multigrain toast"],
                        ['Mid-morning', '11:00 AM · ~120 kcal · 4 g protein', "1 fruit\n1 glass buttermilk"],
                        ['Lunch', '1:30 PM · ~510 kcal · 36 g protein', "1 cup brown rice, or 2 rotis\n120 g chicken curry (home-style, little oil)\n1 katori vegetable sabzi\nSalad"],
                        ['Evening snack', '5:00 PM · ~110 kcal · 6 g protein', "30 g roasted chana\nGreen tea"],
                        ['Dinner', '8:00 PM · ~340 kcal · 34 g protein', "150 g grilled fish or chicken breast with lemon and herbs\nSautéed vegetables\n1 bowl clear soup"],
                        ['Bedtime (optional)', '10:00 PM · ~120 kcal · 6 g protein', '1 glass toned milk, no sugar (turmeric optional)'],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-wl-nonveg-1800',
                    'title' => '1800 kcal Fat Loss (Men, Non-Veg)',
                    'summary' => 'For active men aged 25–45 losing fat while lifting. ~130 g protein (about 1.6–1.8 g/kg for a 75–80 kg person).',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 1800,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~370 kcal · 32 g protein', "Omelette: 2 whole eggs + 4 egg whites with vegetables\n2 slices multigrain toast"],
                        ['Mid-morning', '11:00 AM · ~195 kcal · 6 g protein', "1 fruit\n20 g roasted peanuts"],
                        ['Lunch', '1:30 PM · ~580 kcal · 51 g protein', "2 rotis\n150 g chicken curry\n1 katori dal\nSalad"],
                        ['Evening snack', '5:00 PM · ~150 kcal · 9 g protein', 'Sprouts chaat (1 katori), or 2 boiled egg whites + 1 fruit'],
                        ['Dinner', '8:30 PM · ~380 kcal · 36 g protein', "150 g grilled fish or tandoori chicken\n1 roti\nMixed vegetables"],
                        ['Bedtime', '10:30 PM · ~120 kcal · 6 g protein', '1 glass toned milk'],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-wl-egg-1500',
                    'title' => '1500 kcal Eggetarian Fat Loss',
                    'summary' => 'Vegetarian Indian meals plus eggs for easy protein (~85 g). No meat or fish. Steady loss of about 0.5 kg a week.',
                    'level' => 'beginner', 'duration_weeks' => 12, 'daily_calories' => 1500,
                    'sections' => [
                        ['Early morning', '6:30 AM · ~60 kcal', "1 glass warm water\n5 soaked almonds"],
                        ['Breakfast', '8:30 AM · ~300 kcal · 22 g protein', "Masala omelette: 1 whole egg + 3 egg whites with onion, tomato and coriander\n1 multigrain toast or 1 small roti\n1 cup green tea or tea without sugar"],
                        ['Mid-morning', '11:00 AM · ~120 kcal · 4 g protein', "1 seasonal fruit\n1 glass buttermilk"],
                        ['Lunch', '1:30 PM · ~450 kcal · 20 g protein', "2 multigrain rotis\n1 katori dal\n1 katori green vegetable sabzi\nSalad\n1 small bowl curd"],
                        ['Evening snack', '5:00 PM · ~150 kcal · 12 g protein', "2 boiled eggs (or 1 katori sprouts chaat)\nLemon, pepper and chaat masala"],
                        ['Dinner', '8:00 PM · ~380 kcal · 25 g protein', "Egg bhurji (1 whole egg + 2 whites) or 75 g paneer bhurji\n1 roti\n1 cup sautéed vegetables\n1 bowl clear soup"],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-wl-vegan-1400',
                    'title' => '1400 kcal Vegan Fat Loss',
                    'summary' => 'Fully plant-based deficit plan with ~75 g protein from tofu, soya, dals and sprouts. No dairy, eggs, meat or honey.',
                    'level' => 'beginner', 'duration_weeks' => 12, 'daily_calories' => 1400,
                    'sections' => [
                        ['Early morning', '6:30 AM · ~60 kcal', "1 glass warm water\n5 soaked almonds + 1 walnut"],
                        ['Breakfast', '8:30 AM · ~280 kcal · 16 g protein', "2 besan or moong dal chillas with vegetables\nMint-coriander chutney\nBlack coffee or tea with soy milk, no sugar"],
                        ['Mid-morning', '11:00 AM · ~110 kcal · 5 g protein', "1 fruit\n1 glass unsweetened soy milk (200 ml) or a handful of roasted chana"],
                        ['Lunch', '1:30 PM · ~420 kcal · 20 g protein', "1 cup brown rice or 2 jowar rotis\n1 katori rajma or chole\n1 katori vegetable sabzi\nLarge salad with lemon"],
                        ['Evening snack', '5:00 PM · ~140 kcal · 9 g protein', '1 katori sprouts chaat, or 30 g roasted peanuts and chana mix'],
                        ['Dinner', '8:00 PM · ~360 kcal · 25 g protein', "120 g tofu or 40 g soya chunks stir-fried with capsicum, onion and beans\n1 roti\n1 bowl vegetable soup"],
                        ['Daily vegan essentials', 'Check with your doctor', "Use B12-fortified foods or a B12 supplement.\nTake 1 tablespoon ground flax or chia daily for omega-3.\nGet calcium from ragi, tofu (set with calcium), til and fortified soy milk."],
                        $dailyGuidelines,
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-muscle-gain',
            'name' => 'Muscle Gain',
            'description' => 'Calorie surplus with 1.6–2 g protein per kg body weight, spread across 5–6 meals.',
            'plans' => [
                [
                    'key' => 'diet-mg-veg-2600',
                    'title' => '2600 kcal Vegetarian Muscle Gain',
                    'summary' => 'Food-first vegetarian bulk built on dairy, pulses and paneer, ~110 g protein. Aim to gain 0.25–0.5 kg a week.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2600,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~610 kcal · 22 g protein', "60 g oats cooked in 300 ml milk\n1 banana\n1 tablespoon peanut butter"],
                        ['Mid-morning', '11:00 AM · ~330 kcal · 15 g protein', 'Paneer sandwich: 2 slices multigrain bread, 50 g paneer, vegetables and mint chutney'],
                        ['Lunch', '1:30 PM · ~785 kcal · 37 g protein', "3 rotis + 1 katori rice\n1½ katori rajma or chole\n150 g curd\nSalad"],
                        ['Pre-workout', '5:00 PM · ~200 kcal', '1 banana + 4 dates (60–90 minutes before training)'],
                        ['Post-workout dinner', '8:00 PM · ~525 kcal · 26 g protein', "100 g paneer bhurji\n2 rotis\n1 katori mixed vegetables"],
                        ['Bedtime', '10:30 PM · ~220 kcal · 10 g protein', "1 glass milk\n10 almonds"],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-mg-nonveg-2750',
                    'title' => '2750 kcal Non-Veg Lean Bulk',
                    'summary' => 'Lean bulk for people training 4–5× a week, ~160 g protein, with carbohydrates timed around the workout.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2750,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~655 kcal · 38 g protein', "2 whole eggs + 3 egg whites omelette\n2 slices toast\n1 banana\n300 ml milk"],
                        ['Mid-morning', '11:00 AM · ~300 kcal · 7 g protein', '1 plate vegetable poha with peanuts'],
                        ['Lunch', '1:30 PM · ~710 kcal · 52 g protein', "1½ cups rice\n150 g chicken curry\n1 katori dal\nSalad"],
                        ['Pre-workout', '5:00 PM · ~280 kcal · 7 g protein', '1 toast with 1 tablespoon peanut butter + 1 banana'],
                        ['Post-workout dinner', '8:00 PM · ~600 kcal · 49 g protein', "150 g fish or chicken\n2 rotis\nVegetables\n150 g curd"],
                        ['Bedtime', '10:30 PM · ~190 kcal · 8 g protein', "200 ml milk\n10 almonds"],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-mg-egg-2600',
                    'title' => '2600 kcal Eggetarian Muscle Gain',
                    'summary' => 'Vegetarian bulk with eggs every day for ~130 g protein. Aim to gain 0.25–0.5 kg a week while training 4–5× a week.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2600,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~600 kcal · 35 g protein', "3 whole eggs scrambled or omelette\n2 slices multigrain toast\n1 banana\n200 ml milk"],
                        ['Mid-morning', '11:00 AM · ~320 kcal · 14 g protein', "1 bowl (150 g) curd with 30 g muesli or oats\n1 fruit"],
                        ['Lunch', '1:30 PM · ~720 kcal · 30 g protein', "3 rotis + ½ cup rice\n1½ katori dal or rajma\n1 katori vegetable sabzi\nSalad"],
                        ['Pre-workout', '5:00 PM · ~250 kcal · 8 g protein', '1 banana + 1 tablespoon peanut butter on 1 toast'],
                        ['Post-workout dinner', '8:00 PM · ~520 kcal · 33 g protein', "Egg curry (3 eggs) or 100 g paneer\n2 rotis\n1 katori mixed vegetables"],
                        ['Bedtime', '10:30 PM · ~200 kcal · 10 g protein', "1 glass milk\n10 almonds"],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-mg-vegan-2500',
                    'title' => '2500 kcal Vegan Muscle Gain',
                    'summary' => 'Plant-based surplus with ~120 g protein from soya, tofu, dals, peanuts and seeds, spread across 6 meals.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2500,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~560 kcal · 25 g protein', "60 g oats cooked in 300 ml soy milk\n1 banana\n1 tablespoon peanut butter + 1 tablespoon chia seeds"],
                        ['Mid-morning', '11:00 AM · ~300 kcal · 15 g protein', 'Tofu sandwich: 2 slices multigrain bread, 80 g tofu, vegetables and chutney'],
                        ['Lunch', '1:30 PM · ~700 kcal · 30 g protein', "1 cup rice + 2 rotis\n1½ katori chole or rajma\n1 katori vegetable sabzi\nSalad"],
                        ['Pre-workout', '5:00 PM · ~220 kcal · 5 g protein', '1 banana + 4 dates'],
                        ['Post-workout dinner', '8:00 PM · ~520 kcal · 35 g protein', "50 g soya chunks curry or 150 g tofu\n2 rotis\n1 katori mixed vegetables"],
                        ['Bedtime', '10:30 PM · ~200 kcal · 10 g protein', "1 glass soy milk\n15 g roasted peanuts or pumpkin seeds"],
                        ['Daily vegan essentials', 'Check with your doctor', "Use B12-fortified foods or a B12 supplement.\nTake 1 tablespoon ground flax or chia daily for omega-3.\nGet calcium from ragi, tofu (set with calcium), til and fortified soy milk."],
                        $dailyGuidelines,
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-balanced',
            'name' => 'Balanced & Maintenance',
            'description' => 'Everyday healthy eating based on the ICMR-NIN "My Plate for the Day" model.',
            'plans' => [
                [
                    'key' => 'diet-bal-1900',
                    'title' => 'Balanced Indian Plate (~1900 kcal)',
                    'summary' => 'Maintain weight and health: half the plate vegetables and fruit, plus cereals and millets, pulses, dairy, and nuts and seeds, as ICMR-NIN recommends.',
                    'level' => 'beginner', 'duration_weeks' => null, 'daily_calories' => 1900,
                    'sections' => [
                        ['The plate model', 'How to build every main meal', "• ½ plate: vegetables (including green leafy) and fruit\n• ¼ plate: cereals and millets (roti, rice, jowar, bajra, ragi)\n• ¼ plate: pulses, eggs, fish, chicken or paneer\n• Plus curd or milk, and a small handful of nuts or seeds"],
                        ['Breakfast', '8:00 AM · ~295 kcal · 10 g protein', "2 idlis + 1 katori sambar\n1 tablespoon coconut chutney"],
                        ['Mid-morning', '11:00 AM · ~150 kcal · 3 g protein', "1 fruit\n10 almonds"],
                        ['Lunch', '1:30 PM · ~600 kcal · 22 g protein', "2 rotis + 1 katori rice\n1 katori dal\n1 katori green leafy vegetable (palak or methi)\nSalad + 100 g curd"],
                        ['Evening', '5:00 PM · ~200 kcal · 9 g protein', "Sprouts chaat\nTea with less sugar"],
                        ['Dinner', '8:00 PM · ~490 kcal · 18 g protein', "2 rotis\nEgg curry or paneer curry\nMixed vegetable\nSalad"],
                        ['Bedtime', '10:00 PM · ~120 kcal · 6 g protein', '1 cup toned milk'],
                        $dailyGuidelines,
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-lean',
            'name' => 'Lean & Toning',
            'description' => 'Around maintenance calories with high protein (1.6–2 g/kg) to lose fat and build muscle at the same time (recomposition).',
            'plans' => [
                [
                    'key' => 'diet-lean-veg-2000',
                    'title' => '2000 kcal Vegetarian Lean & Toning',
                    'summary' => 'Maintenance-level vegetarian plan with ~100 g protein from paneer, curd, dals and soya. Pair with strength training 3–4× a week.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2000,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~420 kcal · 22 g protein', "2 paneer or moong dal chillas (or 1 plate vegetable upma with peanuts)\n150 g hung curd with cucumber\n1 cup tea without sugar"],
                        ['Mid-morning', '11:00 AM · ~180 kcal · 8 g protein', "1 fruit\n1 glass buttermilk\n10 almonds"],
                        ['Lunch', '1:30 PM · ~560 kcal · 25 g protein', "2 rotis + ½ cup rice\n1 katori dal\n1 katori soya or paneer sabzi\nSalad\n1 small bowl curd"],
                        ['Pre / post-workout', '5:30 PM · ~230 kcal · 15 g protein', "1 banana\n1 glass milk, or 1 katori roasted chana"],
                        ['Dinner', '8:30 PM · ~480 kcal · 30 g protein', "100 g paneer tikka or tofu tikka\n1 roti\n1 bowl vegetables\n1 bowl dal soup"],
                        ['Bedtime (optional)', '10:30 PM · ~130 kcal · 6 g protein', '1 glass toned milk with turmeric'],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-lean-egg-2000',
                    'title' => '2000 kcal Eggetarian Lean & Toning',
                    'summary' => 'Vegetarian food plus eggs, ~115 g protein, around maintenance calories for a leaner, firmer body.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2000,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~430 kcal · 28 g protein', "2 whole eggs + 2 egg whites omelette with vegetables\n2 slices multigrain toast\n1 fruit"],
                        ['Mid-morning', '11:00 AM · ~180 kcal · 9 g protein', "150 g curd with 1 tablespoon seeds\n5 almonds"],
                        ['Lunch', '1:30 PM · ~550 kcal · 24 g protein', "2 rotis + ½ cup rice\n1 katori dal or rajma\n1 katori vegetable sabzi\nSalad"],
                        ['Pre / post-workout', '5:30 PM · ~220 kcal · 13 g protein', "1 banana\n2 boiled eggs"],
                        ['Dinner', '8:30 PM · ~480 kcal · 32 g protein', "Egg curry (2 eggs) or 100 g paneer bhurji\n1 roti\n1 bowl sautéed vegetables"],
                        ['Bedtime (optional)', '10:30 PM · ~130 kcal · 6 g protein', '1 glass toned milk'],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-lean-nonveg-2100',
                    'title' => '2100 kcal Non-Veg Lean & Toning',
                    'summary' => 'High-protein (~140 g) maintenance plan with eggs, chicken and fish. Lose fat while building muscle.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2100,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~430 kcal · 30 g protein', "2 whole eggs + 3 egg whites omelette\n2 slices multigrain toast\n1 fruit"],
                        ['Mid-morning', '11:00 AM · ~180 kcal · 8 g protein', "1 glass buttermilk\n20 g roasted peanuts"],
                        ['Lunch', '1:30 PM · ~600 kcal · 45 g protein', "1 cup rice or 2 rotis\n150 g chicken curry (home-style, little oil)\n1 katori dal\nSalad"],
                        ['Pre / post-workout', '5:30 PM · ~220 kcal · 12 g protein', "1 banana\n1 glass milk"],
                        ['Dinner', '8:30 PM · ~520 kcal · 42 g protein', "150 g grilled fish or tandoori chicken\n1 roti\n1 bowl vegetables\n1 small bowl curd"],
                        ['Bedtime (optional)', '10:30 PM · ~150 kcal · 6 g protein', "1 glass toned milk\n5 almonds"],
                        $dailyGuidelines,
                    ],
                ],
                [
                    'key' => 'diet-lean-vegan-2000',
                    'title' => '2000 kcal Vegan Lean & Toning',
                    'summary' => 'Plant-based maintenance plan with ~100 g protein from tofu, soya, tempeh-style chunks, dals and seeds.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 2000,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~420 kcal · 22 g protein', "Tofu bhurji (120 g) with vegetables\n2 slices multigrain toast or 1 roti\n1 fruit"],
                        ['Mid-morning', '11:00 AM · ~200 kcal · 10 g protein', "1 glass soy milk\n1 tablespoon pumpkin seeds + 1 fruit"],
                        ['Lunch', '1:30 PM · ~560 kcal · 24 g protein', "2 jowar or bajra rotis + ½ cup rice\n1½ katori dal or chole\n1 katori vegetable sabzi\nSalad"],
                        ['Pre / post-workout', '5:30 PM · ~230 kcal · 12 g protein', "1 banana\n1 katori roasted chana"],
                        ['Dinner', '8:30 PM · ~470 kcal · 30 g protein', "40 g soya chunks pulao or soya curry\n1 roti\n1 bowl vegetable soup"],
                        ['Daily vegan essentials', 'Check with your doctor', "Use B12-fortified foods or a B12 supplement.\nTake 1 tablespoon ground flax or chia daily for omega-3.\nGet calcium from ragi, tofu (set with calcium), til and fortified soy milk."],
                        $dailyGuidelines,
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-diabetes',
            'name' => 'Diabetes-friendly',
            'description' => 'Low-GI meals with fewer refined carbs and more protein and fibre (ICMR-INDIAB approach).',
            'plans' => [
                [
                    'key' => 'diet-dm-veg-1600',
                    'title' => '1600 kcal Diabetic-friendly (Low-GI Vegetarian)',
                    'summary' => 'Smaller, regular meals with millets, pulses and lots of non-starchy vegetables. Roughly 50–55% of calories from carbs, ~20% from protein.',
                    'level' => 'beginner', 'duration_weeks' => 12, 'daily_calories' => 1600,
                    'sections' => [
                        $medicalNote,
                        ['Early morning', '6:30 AM · ~80 kcal', "1 glass water\n5 almonds + 2 walnut halves"],
                        ['Breakfast', '8:00 AM · ~300 kcal · 16 g protein', "2 vegetable besan chillas\nGreen chutney\n100 g curd"],
                        ['Mid-morning', '11:00 AM · ~110 kcal · 3 g protein', "1 guava or 1 small apple\n1 glass buttermilk"],
                        ['Lunch', '1:30 PM · ~510 kcal · 19 g protein', "2 jowar or bajra rotis\n1 katori dal\n1 katori non-starchy sabzi (bhindi, lauki, beans)\nLarge salad, eaten first\n100 g curd"],
                        ['Evening snack', '5:00 PM · ~110 kcal · 6 g protein', "30 g roasted chana\nGreen tea or tea without sugar"],
                        ['Dinner', 'By 8:00 PM · ~360 kcal · 21 g protein', "1 multigrain roti\n100 g low-fat paneer or tofu bhurji\nSautéed vegetables"],
                        ['Bedtime', '10:00 PM · ~100 kcal · 5 g protein', '1 cup toned milk, no sugar'],
                        ['Diabetes tips', 'Every day', "• No sugar, honey, jaggery, fruit juices or sweets.\n• Eat vegetables and protein first, carbs last.\n• Walk 10–15 minutes after main meals.\n• Choose whole fruits over juice, 2 servings a day.\n• Keep meal timings regular and check your blood sugar as your doctor advises."],
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-pcos',
            'name' => 'PCOS-friendly',
            'description' => 'Low-GI, high-fibre, protein-rich meals that support insulin sensitivity and weight management.',
            'plans' => [
                [
                    'key' => 'diet-pcos-1500',
                    'title' => '1500 kcal PCOS-friendly (Low-GI, High-Protein)',
                    'summary' => 'Whole grains and millets instead of refined flour, protein at every meal, and plenty of vegetables. Combine with strength training 3× a week.',
                    'level' => 'beginner', 'duration_weeks' => 12, 'daily_calories' => 1500,
                    'sections' => [
                        $medicalNote,
                        ['Breakfast', '8:00 AM · ~250 kcal · 15 g protein', "2-egg vegetable omelette + 1 multigrain toast\nVeg option: 2 besan or moong chillas"],
                        ['Mid-morning', '11:00 AM · ~150 kcal · 4 g protein', "1 fruit (berries, apple or pear)\n1 tablespoon pumpkin or flax seeds"],
                        ['Lunch', '1:30 PM · ~400 kcal · 15 g protein', "1 bowl millet or quinoa vegetable khichdi\n1 katori curd\nSalad"],
                        ['Evening snack', '5:00 PM · ~150 kcal · 9 g protein', 'Sprouts chaat or roasted makhana (25 g)'],
                        ['Dinner', '8:00 PM · ~380 kcal · 28 g protein', "120 g grilled chicken or paneer tikka\nSautéed vegetables\nClear soup"],
                        ['Bedtime', '10:00 PM · ~120 kcal · 6 g protein', '1 cup toned milk with turmeric, no sugar'],
                        ['PCOS tips', 'Every day', "• Avoid maida, sugary drinks and bakery items.\n• Have protein with every meal and snack.\n• Aim for 25–30 g of fibre a day from vegetables, pulses and whole grains.\n• Even 5–10% weight loss can improve cycles and symptoms."],
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-vegan',
            'name' => 'Vegan & Plant-based',
            'description' => 'Dairy-free, high-protein plans using tofu, soy, pulses and seeds.',
            'plans' => [
                [
                    'key' => 'diet-vegan-1800',
                    'title' => '1800 kcal Vegan High-Protein',
                    'summary' => 'Completely plant-based with ~95 g protein from tofu, soya chunks, chana, dals and seeds.',
                    'level' => 'intermediate', 'duration_weeks' => 12, 'daily_calories' => 1800,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~370 kcal · 28 g protein', "Tofu bhurji (150 g tofu) with vegetables\n2 slices multigrain toast"],
                        ['Mid-morning', '11:00 AM · ~165 kcal · 5 g protein', "1 fruit\n15 g pumpkin seeds"],
                        ['Lunch', '1:30 PM · ~580 kcal · 22 g protein', "2 rotis\n1½ katori chana masala\nVegetable sabzi\nSalad"],
                        ['Evening snack', '5:00 PM · ~150 kcal · 15 g protein', 'Soya chunk chaat (30 g dry soya chunks, boiled) with onion, tomato and lemon'],
                        ['Dinner', '8:00 PM · ~440 kcal · 21 g protein', "1 cup brown rice\n1 katori dal\nPalak tofu (100 g tofu)"],
                        ['Bedtime', '10:00 PM · ~80 kcal · 6 g protein', '200 ml unsweetened soy milk'],
                        ['Vegan tips', '', "• Combine cereals with pulses (dal-rice, roti-chana) for complete protein.\n• Get vitamin B12 checked; most vegans need a supplement, so ask your doctor.\n• Include calcium-rich foods: ragi, sesame, tofu set with calcium."],
                        $dailyGuidelines,
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-regional',
            'name' => 'Regional Indian',
            'description' => 'Familiar regional meals adapted for fitness goals.',
            'plans' => [
                [
                    'key' => 'diet-south-1500',
                    'title' => '1500 kcal South Indian Fat Loss',
                    'summary' => 'Pesarattu, sambar, rasam and sundal: South Indian favourites with controlled rice portions, ~60 g protein.',
                    'level' => 'beginner', 'duration_weeks' => 12, 'daily_calories' => 1500,
                    'sections' => [
                        ['Breakfast', '8:00 AM · ~280 kcal · 14 g protein', "2 pesarattu (moong dosa)\n2 tablespoons coconut-peanut chutney"],
                        ['Mid-morning', '11:00 AM · ~120 kcal · 4 g protein', "1 glass neer mor (spiced buttermilk)\n1 fruit"],
                        ['Lunch', '1:30 PM · ~500 kcal · 16 g protein', "1 cup rice (150 g cooked)\n1 katori sambar + rasam\n1 katori vegetable poriyal\n100 g curd"],
                        ['Evening snack', '5:00 PM · ~150 kcal · 8 g protein', '1 katori chana or moong sundal'],
                        ['Dinner', '8:00 PM · ~440 kcal · 20 g protein', "2 ragi rotis or chapatis\nEgg curry (2 eggs) or paneer curry\nVegetable stir-fry"],
                        $dailyGuidelines,
                    ],
                ],
            ],
        ],

        [
            'key' => 'diet-workout-nutrition',
            'name' => 'Workout Nutrition',
            'description' => 'What to eat before and after training, and how to stay hydrated.',
            'plans' => [
                [
                    'key' => 'diet-pre-post-guide',
                    'title' => 'Pre & Post-workout Nutrition Guide',
                    'summary' => 'Simple, food-first options to fuel training and recover well. Fits alongside any diet chart.',
                    'level' => null, 'duration_weeks' => null, 'daily_calories' => null,
                    'sections' => [
                        ['Pre-workout (60–90 min before)', 'Easy-to-digest carbs + a little protein', "• 1 banana + 4–5 dates\n• 1 slice toast with peanut butter\n• Small bowl of poha or upma\n• Curd with fruit\nAvoid heavy, oily or very high-fibre food just before training."],
                        ['Early-morning training', 'If you train within 30 minutes of waking', '1 banana or a handful of dates with water is enough. Have a proper breakfast after the workout.'],
                        ['During the workout', 'Hydration', 'Sip water through the session. For sessions over 60 minutes or in hot weather, add an electrolyte drink or nimbu-paani with a pinch of salt.'],
                        ['Post-workout (within 1–2 hours)', '20–40 g protein + carbohydrates', "• 3–4 eggs + 2 rotis\n• 150 g chicken or fish + rice\n• 100 g paneer + 2 rotis\n• Dal + rice + curd\n• Sprouts chaat + 1 glass milk"],
                        ['Protein through the day', '1.4–2.0 g per kg body weight', 'Spread protein over 4–5 meals, about every 3–4 hours, instead of one large serving. Most people meet their needs from food; ICMR-NIN advises against routine protein supplements, so check with a qualified professional before using them.'],
                    ],
                ],
            ],
        ],
    ]],

    // =====================================================================
    // WORKOUT PLANS
    // =====================================================================
    'workout' => ['categories' => [

        [
            'key' => 'wo-beginner-full-body',
            'name' => 'Beginner — Full Body',
            'description' => '3 days a week, alternating Workout A and B (e.g. Mon A · Wed B · Fri A). Ideal for the first 2–3 months.',
            'plans' => [
                [
                    'key' => 'wo-fb-a',
                    'title' => 'Full Body A (Beginner)',
                    'summary' => 'Use a weight you can lift for the reps with 2–3 reps still in reserve. When you hit the top of the rep range on all sets, add a little weight next time.',
                    'level' => 'beginner', 'duration_weeks' => 8,
                    'sections' => [
                        ['Warm-up', '5–8 minutes', "5 min brisk treadmill walk or cycle\n10 bodyweight squats · 10 arm circles each way · 10 hip hinges"],
                        ['Goblet squat', '3 sets × 10–12 reps · rest 90 s', 'Hold a dumbbell at your chest. Sit down between your hips, knees tracking over your toes, chest up. Drive up through the whole foot.'],
                        ['Dumbbell bench press', '3 × 10–12 · rest 90 s', 'Shoulder blades squeezed together, feet flat. Lower the dumbbells to chest level and press up without locking the elbows hard.'],
                        ['Lat pulldown', '3 × 10–12 · rest 90 s', 'Grip slightly wider than your shoulders. Pull the bar to your upper chest by driving your elbows down. Don\'t lean back excessively.'],
                        ['Dumbbell Romanian deadlift', '3 × 10–12 · rest 90 s', 'Soft knees, push your hips back, dumbbells close to your legs, back flat. Stop when you feel a hamstring stretch, then squeeze your glutes to stand.'],
                        ['Seated dumbbell shoulder press', '2 × 10–12 · rest 60 s', 'Back supported, core braced. Press overhead and lower to ear level.'],
                        ['Plank', '3 × 20–40 seconds', 'Elbows under shoulders, body in a straight line. Squeeze your glutes and don\'t let your hips sag.'],
                        ['Cool-down', '5 minutes', 'Easy walk, then hold quad, hamstring, chest and lat stretches for 30 seconds each.'],
                    ],
                ],
                [
                    'key' => 'wo-fb-b',
                    'title' => 'Full Body B (Beginner)',
                    'summary' => 'The partner session to Full Body A. Same rules: controlled reps and gradual progression.',
                    'level' => 'beginner', 'duration_weeks' => 8,
                    'sections' => [
                        ['Warm-up', '5–8 minutes', "5 min cross-trainer or cycle\nBand pull-aparts × 15 · glute bridges × 10 · cat-cow × 8"],
                        ['Leg press', '3 × 10–12 · rest 90 s', 'Feet hip-width apart in the middle of the platform. Lower until your knees reach about 90°, keeping your lower back flat on the pad.'],
                        ['Seated cable row', '3 × 10–12 · rest 90 s', 'Sit tall and pull the handle to your lower ribs, squeezing your shoulder blades together. Return slowly.'],
                        ['Incline dumbbell press', '3 × 10–12 · rest 90 s', 'Bench at 30°. Press up and slightly in, then lower under control.'],
                        ['Lying or seated leg curl', '3 × 10–12 · rest 60 s', 'Curl the pad towards your glutes, pause, and lower slowly over 2–3 seconds.'],
                        ['Dumbbell lateral raise', '2 × 12–15 · rest 60 s', 'Slight bend in the elbows. Raise to shoulder height leading with the elbows, using light weight and no swinging.'],
                        ['Dead bug', '3 × 8 each side', 'Lower back pressed into the floor. Extend the opposite arm and leg slowly, then return.'],
                        ['Cool-down', '5 minutes', 'Walk, then stretch your hips, hamstrings, chest and upper back.'],
                    ],
                ],
                [
                    'key' => 'wo-home-bodyweight',
                    'title' => 'Home Bodyweight Workout (No Equipment)',
                    'summary' => 'A full-body circuit you can do anywhere. Do 3 rounds: 40 s work / 20 s rest per exercise, 1–2 minutes rest between rounds.',
                    'level' => 'beginner', 'duration_weeks' => 6,
                    'sections' => [
                        ['Warm-up', '5 minutes', 'Marching on the spot, jumping jacks (or step-jacks), arm swings, hip circles.'],
                        ['Bodyweight squat', '40 s work · 20 s rest', 'Sit back as if onto a chair, chest up, and stand tall at the top.'],
                        ['Push-up', '40 s · 20 s rest', 'Hands under your shoulders, body straight. Make it easier with hands on a bed or wall, harder on your toes.'],
                        ['Reverse lunge', '40 s (alternate legs) · 20 s rest', 'Step back and lower until both knees are about 90°. Push through the front heel to return.'],
                        ['Glute bridge', '40 s · 20 s rest', 'Drive through your heels, squeeze your glutes at the top, and hold for 1 second.'],
                        ['Superman', '40 s · 20 s rest', 'Lying face down, lift your arms and legs slightly, hold for 2 seconds and lower.'],
                        ['Mountain climbers', '40 s · 20 s rest', 'Hands under your shoulders and hips level. Drive your knees towards your chest at a steady pace.'],
                        ['Cool-down', '5 minutes', 'Child\'s pose, hamstring stretch, hip flexor stretch, chest stretch.'],
                    ],
                ],
            ],
        ],

        [
            'key' => 'wo-ppl',
            'name' => 'Push / Pull / Legs',
            'description' => 'Classic split for intermediates: 3 days a week (once through) or 6 days (twice through) with good recovery.',
            'plans' => [
                [
                    'key' => 'wo-ppl-push',
                    'title' => 'Push Day — Chest, Shoulders, Triceps',
                    'summary' => 'Heavy compound press first, then volume work. Leave 1–2 reps in reserve on most sets.',
                    'level' => 'intermediate', 'duration_weeks' => 10,
                    'sections' => [
                        ['Warm-up', '8 minutes', "5 min cardio + band pull-aparts\n2–3 light warm-up sets of bench press"],
                        ['Barbell bench press', '4 × 6–8 · rest 2–3 min', 'Grip just outside your shoulders, arch your upper back slightly, and keep your feet planted. Touch the bar to your mid-chest and press up and slightly back.'],
                        ['Standing overhead press', '3 × 8–10 · rest 2 min', 'Brace your glutes and core. Press the bar overhead, moving your head through at the top, and don\'t lean back.'],
                        ['Incline dumbbell press', '3 × 8–12 · rest 90 s', 'Bench at 30°. Use a full range of motion with a controlled descent.'],
                        ['Cable fly', '3 × 12–15 · rest 60 s', 'Slight bend in the elbows. Hug a "big tree" and squeeze your chest at the end.'],
                        ['Dumbbell lateral raise', '3 × 12–15 · rest 60 s', 'Light weight with strict form. Raise to shoulder height.'],
                        ['Rope triceps pushdown', '3 × 10–12 · rest 60 s', 'Elbows pinned to your sides. Split the rope at the bottom.'],
                        ['Overhead dumbbell triceps extension', '2 × 12–15 · rest 60 s', 'Elbows pointing forward. Stretch fully behind your head.'],
                        ['Cool-down', '5 minutes', 'Doorway chest stretch, overhead triceps stretch, shoulder cross-body stretch.'],
                    ],
                ],
                [
                    'key' => 'wo-ppl-pull',
                    'title' => 'Pull Day — Back & Biceps',
                    'summary' => 'Horizontal and vertical pulls for a thick, wide back, plus rear delts and biceps.',
                    'level' => 'intermediate', 'duration_weeks' => 10,
                    'sections' => [
                        ['Warm-up', '8 minutes', "5 min rowing machine\nScapular pull-ups × 10 · band face pulls × 15"],
                        ['Barbell bent-over row', '4 × 6–8 · rest 2 min', 'Hinge to about 45° with a flat back. Pull the bar to your lower ribs and squeeze your shoulder blades together.'],
                        ['Pull-ups (or assisted / lat pulldown)', '3 × 6–10 · rest 2 min', 'Start from a dead hang and pull your chest towards the bar. Use an assisted machine or band if needed.'],
                        ['Seated cable row', '3 × 10–12 · rest 90 s', 'Sit tall and pull to your belly button. Pause 1 second with your shoulder blades squeezed.'],
                        ['Face pull', '3 × 12–15 · rest 60 s', 'Rope at eye level. Pull towards your forehead with your elbows high and rotate your hands out.'],
                        ['Dumbbell biceps curl', '3 × 10–12 · rest 60 s', 'Elbows still and no swinging. Lower slowly.'],
                        ['Hammer curl', '2 × 12 · rest 60 s', 'Neutral grip; works the brachialis and forearms.'],
                        ['Cool-down', '5 minutes', 'Lat stretch, biceps wall stretch, child\'s pose.'],
                    ],
                ],
                [
                    'key' => 'wo-ppl-legs',
                    'title' => 'Leg Day — Quads, Hamstrings, Glutes, Calves',
                    'summary' => 'Squat-focused lower-body session with a hip hinge and accessories. Don\'t skip leg day!',
                    'level' => 'intermediate', 'duration_weeks' => 10,
                    'sections' => [
                        ['Warm-up', '10 minutes', "5 min bike\nLeg swings, bodyweight squats × 15, glute bridges × 15, 2–3 light squat sets"],
                        ['Barbell back squat', '4 × 6–8 · rest 2–3 min', 'Brace hard, break at the hips and knees together, and descend to at least parallel. Knees out and chest up.'],
                        ['Romanian deadlift', '3 × 8–10 · rest 2 min', 'Bar close to your legs and back flat. Push your hips back until you feel a strong hamstring stretch.'],
                        ['Leg press', '3 × 10–12 · rest 90 s', 'Full controlled range. Don\'t lock your knees at the top.'],
                        ['Walking lunges', '2 × 12 per leg · rest 90 s', 'Long steps and an upright torso. The back knee lightly touches down.'],
                        ['Leg curl', '3 × 10–12 · rest 60 s', 'Squeeze at full contraction and lower over 3 seconds.'],
                        ['Standing calf raise', '4 × 12–15 · rest 45 s', 'Pause 1 second at the top and stretch fully at the bottom.'],
                        ['Hanging knee raise', '3 × 10–12', 'Curl your pelvis up, avoid swinging.'],
                        ['Cool-down', '5 minutes', 'Quad stretch, hip flexor stretch, hamstring stretch, calf stretch.'],
                    ],
                ],
            ],
        ],

        [
            'key' => 'wo-upper-lower',
            'name' => 'Upper / Lower Split',
            'description' => '4 days a week (Upper · Lower · rest · Upper · Lower). Each muscle trained twice a week for strength and size.',
            'plans' => [
                [
                    'key' => 'wo-ul-upper',
                    'title' => 'Upper Body Day',
                    'summary' => 'Balanced pushing and pulling. Gives each muscle group about 10+ sets a week when done twice weekly.',
                    'level' => 'intermediate', 'duration_weeks' => 10,
                    'sections' => [
                        ['Warm-up', '8 minutes', 'Rowing machine 5 min, band pull-aparts, push-ups × 10.'],
                        ['Barbell bench press', '4 × 6–8 · rest 2–3 min', 'Controlled descent, bar touches mid-chest.'],
                        ['Barbell or T-bar row', '4 × 6–8 · rest 2 min', 'Flat back. Pull to your lower chest.'],
                        ['Overhead press (dumbbell or barbell)', '3 × 8–10 · rest 90 s', 'Core braced with no excessive back arch.'],
                        ['Lat pulldown', '3 × 10–12 · rest 90 s', 'Pull to your upper chest with your elbows driving down.'],
                        ['Dumbbell lateral raise', '3 × 12–15 · rest 60 s', 'Light and strict.'],
                        ['EZ-bar curl + triceps pushdown', '2 × 10–12 each · superset, rest 60 s', 'Do the curl then the pushdown back to back, then rest.'],
                        ['Cool-down', '5 minutes', 'Chest, lats and shoulder stretches.'],
                    ],
                ],
                [
                    'key' => 'wo-ul-lower',
                    'title' => 'Lower Body Day',
                    'summary' => 'Squat and hinge patterns plus single-leg work for balanced legs and strong glutes.',
                    'level' => 'intermediate', 'duration_weeks' => 10,
                    'sections' => [
                        ['Warm-up', '10 minutes', 'Bike 5 min, leg swings, goblet squats × 10, light squat sets.'],
                        ['Back squat', '4 × 6–8 · rest 2–3 min', 'Depth to at least parallel with a neutral spine.'],
                        ['Romanian deadlift', '3 × 8–10 · rest 2 min', 'Hips back and bar close to your legs.'],
                        ['Bulgarian split squat', '3 × 8–10 per leg · rest 90 s', 'Rear foot on the bench, torso slightly forward. Drive through the front heel.'],
                        ['Leg curl', '3 × 10–12 · rest 60 s', 'Slow eccentric.'],
                        ['Calf raise', '3 × 12–15 · rest 45 s', 'Full range with a pause at the top.'],
                        ['Plank or cable crunch', '3 × 30–45 s / 12–15 reps', 'Brace and breathe.'],
                        ['Cool-down', '5 minutes', 'Hip flexor, glute and hamstring stretches.'],
                    ],
                ],
            ],
        ],

        [
            'key' => 'wo-strength',
            'name' => 'Strength (5×5)',
            'description' => 'Heavy compound lifts (~80% of 1-rep max) for building raw strength. Alternate Day A and B, 3 days a week.',
            'plans' => [
                [
                    'key' => 'wo-5x5-a',
                    'title' => 'Strength 5×5 — Day A',
                    'summary' => 'Squat, bench and row. Add 2.5 kg to upper-body lifts and 5 kg to squats each session while your form stays solid. Always warm up with lighter sets first.',
                    'level' => 'intermediate', 'duration_weeks' => 12,
                    'sections' => [
                        ['Warm-up', '10 minutes', "5 min cardio, mobility drills\nRamp-up sets: empty bar × 10, then 2–3 sets of increasing weight × 3–5"],
                        ['Back squat', '5 × 5 · rest 3 min', 'Brace before every rep. Depth to parallel or below.'],
                        ['Bench press', '5 × 5 · rest 3 min', 'Use a spotter or safety pins for heavy sets.'],
                        ['Barbell row', '5 × 5 · rest 2–3 min', 'Strict, controlled reps. Pull to your lower chest.'],
                        ['Plank', '3 × 30–45 s', 'Core accessory.'],
                        ['Cool-down', '5 minutes', 'Light walk and stretches.'],
                    ],
                ],
                [
                    'key' => 'wo-5x5-b',
                    'title' => 'Strength 5×5 — Day B',
                    'summary' => 'Squat, overhead press and deadlift. The deadlift is one heavy working set of 5 after warm-ups.',
                    'level' => 'intermediate', 'duration_weeks' => 12,
                    'sections' => [
                        ['Warm-up', '10 minutes', '5 min cardio, hip and shoulder mobility, ramp-up sets.'],
                        ['Back squat', '5 × 5 · rest 3 min', 'Same weight progression as Day A.'],
                        ['Overhead press', '5 × 5 · rest 3 min', 'Glutes tight and no back lean.'],
                        ['Deadlift', '1 × 5 (after warm-up sets) · rest 3 min', 'Bar over mid-foot and flat back. Push the floor away and lock out with your glutes.'],
                        ['Chin-ups', '3 × as many good reps as possible', 'Use an assisted variation if needed.'],
                        ['Cool-down', '5 minutes', 'Hamstring, hip and lat stretches.'],
                    ],
                ],
            ],
        ],

        [
            'key' => 'wo-fat-loss',
            'name' => 'Fat Loss & Conditioning',
            'description' => 'Circuits, intervals and cardio to burn calories and build fitness. Combine with 2–3 strength sessions a week.',
            'plans' => [
                [
                    'key' => 'wo-hiit-20',
                    'title' => 'HIIT Fat-Burn (20 minutes)',
                    'summary' => '30 s hard / 30 s easy for 10 rounds. Do this 1–2× a week on non-consecutive days. Low-impact swaps are given for beginners.',
                    'level' => 'intermediate', 'duration_weeks' => 6,
                    'sections' => [
                        ['Warm-up', '5 minutes', 'Easy cycling or jogging, then 3 short 10 s speed-ups.'],
                        ['Round 1–2: Bike sprints', '30 s fast · 30 s easy', 'Push hard on the bike. Low-impact: brisk incline walk.'],
                        ['Round 3–4: Kettlebell swings', '30 s · 30 s rest', 'Hinge at the hips and snap your hips forward. Arms just guide the bell.'],
                        ['Round 5–6: Mountain climbers', '30 s · 30 s rest', 'Fast but controlled. Low-impact: slow alternating knee drives.'],
                        ['Round 7–8: Jump squats', '30 s · 30 s rest', 'Land softly. Low-impact: fast bodyweight squats.'],
                        ['Round 9–10: Battle ropes or burpees', '30 s · 30 s rest', 'Keep form clean even when tired. Low-impact: step-back burpees.'],
                        ['Cool-down', '5 minutes', 'Slow walk until breathing settles, then stretch.'],
                    ],
                ],
                [
                    'key' => 'wo-circuit-beginner',
                    'title' => 'Fat-Loss Circuit (Beginner, Low Impact)',
                    'summary' => '3 rounds of 40 s work / 20 s rest, then a 10–15 min incline walk. Joint-friendly with no jumping.',
                    'level' => 'beginner', 'duration_weeks' => 8,
                    'sections' => [
                        ['Warm-up', '5 minutes', 'Marching, arm circles, bodyweight squats.'],
                        ['Step-ups', '40 s (alternate legs) · 20 s rest', 'Use a low step. Drive through the heel of the top foot.'],
                        ['Dumbbell squat to press', '40 s · 20 s rest', 'Squat, then press the dumbbells overhead as you stand.'],
                        ['Dumbbell bent-over row', '40 s · 20 s rest', 'Flat back. Pull to your hips.'],
                        ['Glute bridge', '40 s · 20 s rest', 'Squeeze at the top.'],
                        ['Plank shoulder taps', '40 s · 20 s rest', 'Feet wide for stability and hips still. Knee version for beginners.'],
                        ['Finisher: incline walk', '10–15 minutes', 'Treadmill at 6–10% incline at a brisk pace where you can talk but not sing.'],
                    ],
                ],
                [
                    'key' => 'wo-zone2-cardio',
                    'title' => 'Cardio Plan — Zone 2 (150+ min / week)',
                    'summary' => 'Steady, easy-to-moderate cardio to meet WHO\'s 150–300 minutes a week. Builds heart health and burns fat with low fatigue.',
                    'level' => 'beginner', 'duration_weeks' => null,
                    'sections' => [
                        ['What is Zone 2?', 'The "talk test"', 'You can speak in full sentences but would rather not sing. Roughly 60–70% of max heart rate.'],
                        ['Weekly target', '150–300 minutes', 'For example, 5 sessions of 30–45 minutes, or 3 longer sessions of 50–60 minutes.'],
                        ['Option 1: Incline treadmill walk', '30–45 minutes', 'Incline 6–12% at 4.5–5.5 km/h. Don\'t hold the handrails.'],
                        ['Option 2: Cycling or cross-trainer', '30–45 minutes', 'Steady cadence at a comfortable resistance.'],
                        ['Option 3: Outdoor brisk walk', '45–60 minutes', 'Great on rest days. Count your steps towards a 10,000/day goal.'],
                        ['Progression', 'Every 2 weeks', 'Add 5 minutes per session or one extra session until you reach your weekly target.'],
                    ],
                ],
            ],
        ],

        [
            'key' => 'wo-glutes-legs',
            'name' => 'Glutes & Legs',
            'description' => 'Lower-body shaping and strength focused on the glutes. 2× a week.',
            'plans' => [
                [
                    'key' => 'wo-glute-focus',
                    'title' => 'Glute-Focused Lower Body',
                    'summary' => 'Hip thrusts and hinges for strong, shapely glutes, plus single-leg work. Progress the weight on hip thrusts every week or two.',
                    'level' => 'intermediate', 'duration_weeks' => 10,
                    'sections' => [
                        ['Warm-up', '8 minutes', 'Banded lateral walks × 15 each way, glute bridges × 15, bodyweight squats × 15.'],
                        ['Barbell hip thrust', '4 × 8–12 · rest 90 s', 'Upper back on the bench and chin tucked. Drive through your heels and lock out with your glutes, without arching your lower back.'],
                        ['Goblet squat', '3 × 10–12 · rest 90 s', 'Slow descent, pause at the bottom.'],
                        ['Dumbbell Romanian deadlift', '3 × 10–12 · rest 90 s', 'Hips back and feel the stretch.'],
                        ['Cable glute kickback', '3 × 12–15 per leg · rest 45 s', 'Slight forward lean. Kick back and squeeze without arching your back.'],
                        ['Hip abduction machine', '3 × 15–20 · rest 45 s', 'Lean slightly forward for more glute work.'],
                        ['Step-ups', '3 × 10 per leg · rest 60 s', 'Knee-height box, slow lowering.'],
                        ['Finisher: glute bridge hold', '2 × 30–45 s', 'Hold the top position and squeeze hard.'],
                    ],
                ],
            ],
        ],

        [
            'key' => 'wo-core-mobility',
            'name' => 'Core & Mobility',
            'description' => 'Core stability and mobility work to improve posture, prevent injuries and move better.',
            'plans' => [
                [
                    'key' => 'wo-core-strength',
                    'title' => 'Core Strength',
                    'summary' => 'Anti-extension, anti-rotation and lateral core work. Add it after any workout, 2–3× a week.',
                    'level' => 'beginner', 'duration_weeks' => null,
                    'sections' => [
                        ['Dead bug', '3 × 10 per side', 'Lower back glued to the floor. Move slowly.'],
                        ['Bird dog', '3 × 10 per side', 'Reach long with your opposite arm and leg, keeping your hips level.'],
                        ['Plank', '3 × 30–45 s', 'Squeeze your glutes and quads, and breathe.'],
                        ['Side plank', '3 × 20–30 s per side', 'Hips stacked and lifted. Knees bent for the easier version.'],
                        ['Pallof press', '3 × 10 per side', 'Stand side-on to the cable and press it straight out, resisting the rotation.'],
                        ['Hanging knee raise', '3 × 10–12', 'Curl your pelvis up. Use the captain\'s chair if hanging is hard.'],
                    ],
                ],
                [
                    'key' => 'wo-mobility-routine',
                    'title' => 'Mobility & Stretching (15–20 min)',
                    'summary' => 'Full-body mobility flow for rest days or after training. Hold stretches for 30–60 seconds and breathe slowly.',
                    'level' => 'beginner', 'duration_weeks' => null,
                    'sections' => [
                        ['Cat-cow', '10 slow reps', 'Move through the whole spine.'],
                        ['World\'s greatest stretch', '5 per side', 'Lunge, elbow to instep, then rotate and reach up.'],
                        ['90/90 hip switches', '8 per side', 'Sit tall and rotate your knees side to side.'],
                        ['Thoracic rotations', '8 per side', 'On all fours, hand behind your head, rotate your elbow to the ceiling.'],
                        ['Hip flexor stretch', '45 s per side', 'Half-kneeling, squeeze the glute of the back leg.'],
                        ['Hamstring stretch', '45 s per side', 'Hinge from the hips with a straight back.'],
                        ['Doorway chest stretch', '45 s', 'Forearms on the frame, lean gently through.'],
                        ['Child\'s pose', '60 s', 'Sink your hips back and breathe into your back.'],
                    ],
                ],
            ],
        ],

        [
            'key' => 'wo-low-impact',
            'name' => 'Low-Impact & 45+',
            'description' => 'Safe, joint-friendly strength and balance training for older adults and anyone returning to exercise.',
            'plans' => [
                [
                    'key' => 'wo-low-impact-strength',
                    'title' => 'Low-Impact Strength & Balance',
                    'summary' => '2–3× a week. Builds leg strength, posture and balance. Get medical clearance first if you have heart, joint or blood-pressure conditions.',
                    'level' => 'beginner', 'duration_weeks' => 8,
                    'sections' => [
                        ['Warm-up', '5–8 minutes', 'Easy walk or recumbent bike, shoulder rolls, ankle circles.'],
                        ['Chair sit-to-stand', '3 × 8–12 · rest 60 s', 'Arms crossed if you can. Stand tall and sit down slowly.'],
                        ['Wall or incline push-up', '3 × 8–12 · rest 60 s', 'Body straight. Move your feet further back to make it harder.'],
                        ['Resistance band row', '3 × 12 · rest 60 s', 'Squeeze your shoulder blades. Elbows stay close to your body.'],
                        ['Step-ups (low step)', '2 × 8 per leg · rest 60 s', 'Hold a rail for support if needed.'],
                        ['Standing calf raise', '2 × 12', 'Hold a wall for balance.'],
                        ['Single-leg balance', '3 × 20 s per leg', 'Stand near a wall or chair and progress to eyes closed over time.'],
                        ['Cool-down', '5 minutes', 'Gentle seated stretches for the hamstrings, chest and shoulders.'],
                    ],
                ],
            ],
        ],
    ]],
];
