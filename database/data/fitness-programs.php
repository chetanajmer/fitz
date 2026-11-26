<?php

/*
|--------------------------------------------------------------------------
| Fitnix default programs — day-by-day workout and diet schedules
|--------------------------------------------------------------------------
| A program is a repeating cycle of days. Each day points to a plan in the default
| fitness library (database/data/fitness-library.php) by its key, or is a rest day.
| Importing a program into a gym also imports the plans it uses.
|
| Days: [plan key | null for rest, day title, notes?]
| Keys are permanent (gyms that imported a program are matched by key).
|
| Basis: ACSM / WHO guidance — 150–300 min moderate cardio per week, strength training
| for every major muscle group ≥ 2 days a week, ~48 h before training the same muscles
| hard again, and at least 1–2 full rest days a week.
*/

$rest = [null, 'Rest & recovery', 'Light walk of 20–30 minutes, stretching and good sleep. Recovery is when muscles grow.'];

return [
    'workout' => [
        // ---- Weight loss -------------------------------------------------------
        [
            'key' => 'prog-wo-wl-beginner',
            'title' => 'Weight Loss — Beginner (3 strength + 2 cardio days)',
            'summary' => 'Full-body strength 3× a week to keep muscle, plus easy Zone 2 cardio and a beginner circuit to burn fat. Ideal for the first 8 weeks.',
            'goal' => 'weight_loss', 'level' => 'beginner', 'duration_weeks' => 8,
            'days' => [
                ['wo-fb-a', 'Full body strength A'],
                ['wo-zone2-cardio', 'Zone 2 cardio (30–40 min)'],
                ['wo-fb-b', 'Full body strength B'],
                $rest,
                ['wo-circuit-beginner', 'Fat-loss circuit'],
                ['wo-zone2-cardio', 'Long easy cardio (45 min)'],
                $rest,
            ],
        ],
        [
            'key' => 'prog-wo-wl-intermediate',
            'title' => 'Weight Loss — Intermediate (Upper/Lower + HIIT)',
            'summary' => 'Four strength sessions with two short HIIT finishes and a Zone 2 day. For members training regularly for 3+ months.',
            'goal' => 'weight_loss', 'level' => 'intermediate', 'duration_weeks' => 8,
            'days' => [
                ['wo-ul-upper', 'Upper body strength'],
                ['wo-ul-lower', 'Lower body strength'],
                ['wo-hiit-20', 'HIIT fat-burn (20 min)'],
                ['wo-ul-upper', 'Upper body strength'],
                ['wo-ul-lower', 'Lower body strength'],
                ['wo-zone2-cardio', 'Zone 2 cardio (45 min)', 'Add 10 minutes of core work from the Core Strength plan.'],
                $rest,
            ],
        ],
        [
            'key' => 'prog-wo-wl-home',
            'title' => 'Weight Loss at Home (No Equipment)',
            'summary' => 'Bodyweight strength, HIIT and walking — no gym needed. Good for travel weeks or members who train from home.',
            'goal' => 'home', 'level' => 'beginner', 'duration_weeks' => 6,
            'days' => [
                ['wo-home-bodyweight', 'Bodyweight strength'],
                ['wo-zone2-cardio', 'Brisk walk / easy cardio (30–40 min)'],
                ['wo-hiit-20', 'HIIT (20 min)'],
                ['wo-mobility-routine', 'Mobility & stretching'],
                ['wo-home-bodyweight', 'Bodyweight strength'],
                ['wo-core-strength', 'Core strength'],
                $rest,
            ],
        ],

        // ---- Muscle gain ---------------------------------------------------------
        [
            'key' => 'prog-wo-mg-beginner',
            'title' => 'Muscle Gain — Beginner (Upper/Lower 4 days)',
            'summary' => 'Each muscle trained twice a week with progressive overload. The best first program for building size.',
            'goal' => 'muscle_gain', 'level' => 'beginner', 'duration_weeks' => 12,
            'days' => [
                ['wo-ul-upper', 'Upper body'],
                ['wo-ul-lower', 'Lower body'],
                $rest,
                ['wo-ul-upper', 'Upper body'],
                ['wo-ul-lower', 'Lower body'],
                ['wo-mobility-routine', 'Mobility (optional)'],
                $rest,
            ],
        ],
        [
            'key' => 'prog-wo-mg-ppl',
            'title' => 'Muscle Gain — Push / Pull / Legs (6 days)',
            'summary' => 'Classic hypertrophy split hitting every muscle twice a week with 10+ hard sets. For intermediate lifters who recover well.',
            'goal' => 'muscle_gain', 'level' => 'intermediate', 'duration_weeks' => 12,
            'days' => [
                ['wo-ppl-push', 'Push — chest, shoulders, triceps'],
                ['wo-ppl-pull', 'Pull — back & biceps'],
                ['wo-ppl-legs', 'Legs'],
                ['wo-ppl-push', 'Push — chest, shoulders, triceps'],
                ['wo-ppl-pull', 'Pull — back & biceps'],
                ['wo-ppl-legs', 'Legs'],
                $rest,
            ],
        ],

        // ---- Lean & toning ---------------------------------------------------------
        [
            'key' => 'prog-wo-lean',
            'title' => 'Lean & Toning (Strength + Conditioning)',
            'summary' => 'Strength to shape muscle plus HIIT and core work to stay lean. Popular with members who want a fit, defined look.',
            'goal' => 'lean', 'level' => 'intermediate', 'duration_weeks' => 8,
            'days' => [
                ['wo-ul-upper', 'Upper body'],
                ['wo-glute-focus', 'Glutes & legs'],
                ['wo-hiit-20', 'HIIT (20 min)'],
                ['wo-core-strength', 'Core strength'],
                ['wo-fb-b', 'Full body'],
                ['wo-zone2-cardio', 'Zone 2 cardio (40 min)'],
                $rest,
            ],
        ],
        [
            'key' => 'prog-wo-glutes-toning',
            'title' => 'Glutes & Lower-Body Toning (Women)',
            'summary' => 'Two glute-focused days a week, one upper-body day and cardio for overall shape. Fully beginner-friendly.',
            'goal' => 'lean', 'level' => 'beginner', 'duration_weeks' => 8,
            'days' => [
                ['wo-glute-focus', 'Glute-focused lower body'],
                ['wo-ul-upper', 'Upper body (light)'],
                ['wo-zone2-cardio', 'Cardio (30–40 min)'],
                ['wo-glute-focus', 'Glute-focused lower body'],
                ['wo-core-strength', 'Core strength'],
                ['wo-mobility-routine', 'Mobility & stretching'],
                $rest,
            ],
        ],

        // ---- Strength ---------------------------------------------------------------
        [
            'key' => 'prog-wo-strength-5x5',
            'title' => 'Strength 5×5 (3 days, A/B alternating)',
            'summary' => 'Squat, bench, row, press and deadlift with simple linear progression. Workouts alternate A-B-A, then B-A-B — this program is a 14-day cycle.',
            'goal' => 'strength', 'level' => 'intermediate', 'duration_weeks' => 12,
            'days' => [
                ['wo-5x5-a', 'Strength A'],
                $rest,
                ['wo-5x5-b', 'Strength B'],
                $rest,
                ['wo-5x5-a', 'Strength A'],
                ['wo-mobility-routine', 'Mobility (optional)'],
                $rest,
                ['wo-5x5-b', 'Strength B'],
                $rest,
                ['wo-5x5-a', 'Strength A'],
                $rest,
                ['wo-5x5-b', 'Strength B'],
                ['wo-mobility-routine', 'Mobility (optional)'],
                $rest,
            ],
        ],

        // ---- General fitness / beginners ----------------------------------------------
        [
            'key' => 'prog-wo-general-beginner',
            'title' => 'General Fitness — First 8 Weeks',
            'summary' => 'Learn the basic lifts, build a cardio base and a habit of training. The default program for new members.',
            'goal' => 'general_fitness', 'level' => 'beginner', 'duration_weeks' => 8,
            'days' => [
                ['wo-fb-a', 'Full body A'],
                ['wo-zone2-cardio', 'Easy cardio (30 min)'],
                ['wo-fb-b', 'Full body B'],
                $rest,
                ['wo-core-strength', 'Core + mobility', 'Follow with the Mobility & Stretching routine if time allows.'],
                ['wo-zone2-cardio', 'Easy cardio (30–40 min)'],
                $rest,
            ],
        ],

        // ---- Endurance ------------------------------------------------------------------
        [
            'key' => 'prog-wo-endurance',
            'title' => 'Endurance & Cardio Fitness',
            'summary' => 'Builds stamina with 150–200 minutes of Zone 2 a week, one HIIT day and strength to stay injury-free.',
            'goal' => 'endurance', 'level' => 'intermediate', 'duration_weeks' => 8,
            'days' => [
                ['wo-zone2-cardio', 'Zone 2 cardio (40 min)'],
                ['wo-fb-a', 'Full body strength'],
                ['wo-hiit-20', 'Intervals (HIIT)'],
                ['wo-zone2-cardio', 'Zone 2 cardio (40 min)'],
                ['wo-core-strength', 'Core strength'],
                ['wo-zone2-cardio', 'Long Zone 2 (60 min)'],
                $rest,
            ],
        ],

        // ---- Flexibility -------------------------------------------------------------------
        [
            'key' => 'prog-wo-flexibility',
            'title' => 'Flexibility, Mobility & Posture',
            'summary' => 'Daily mobility with core and light strength days. Great for desk workers, stiffness and as a recovery block.',
            'goal' => 'flexibility', 'level' => 'beginner', 'duration_weeks' => 6,
            'days' => [
                ['wo-mobility-routine', 'Mobility & stretching'],
                ['wo-core-strength', 'Core strength'],
                ['wo-mobility-routine', 'Mobility & stretching'],
                ['wo-low-impact-strength', 'Light strength & balance'],
                ['wo-mobility-routine', 'Mobility & stretching'],
                ['wo-zone2-cardio', 'Easy walk (30 min)'],
                $rest,
            ],
        ],

        // ---- Seniors & low impact -------------------------------------------------------------
        [
            'key' => 'prog-wo-low-impact',
            'title' => 'Seniors & Low Impact (Joint-Friendly)',
            'summary' => 'Gentle strength, balance and walking for 50+ members, beginners with joint pain or those returning after a break.',
            'goal' => 'low_impact', 'level' => 'beginner', 'duration_weeks' => 8,
            'days' => [
                ['wo-low-impact-strength', 'Low-impact strength & balance'],
                ['wo-zone2-cardio', 'Easy walk (20–30 min)'],
                ['wo-mobility-routine', 'Mobility & stretching'],
                ['wo-low-impact-strength', 'Low-impact strength & balance'],
                ['wo-zone2-cardio', 'Easy walk (20–30 min)'],
                $rest,
                $rest,
            ],
        ],
    ],

    'diet' => [
        // ---- Weight loss -----------------------------------------------------------------
        ['key' => 'prog-diet-wl-veg', 'title' => 'Weight Loss — Vegetarian', 'goal' => 'weight_loss', 'diet_preference' => 'veg', 'duration_weeks' => 12,
            'summary' => '~1500 kcal a day, alternating a North Indian and a South Indian menu so meals never get boring.',
            'days' => [['diet-wl-veg-1500', 'Menu A — North Indian'], ['diet-south-1500', 'Menu B — South Indian'], ['diet-wl-veg-1500', 'Menu A — North Indian'], ['diet-south-1500', 'Menu B — South Indian'], ['diet-wl-veg-1500', 'Menu A — North Indian'], ['diet-south-1500', 'Menu B — South Indian'], ['diet-wl-veg-1500', 'Menu A — North Indian']]],
        ['key' => 'prog-diet-wl-veg-1200', 'title' => 'Weight Loss — Vegetarian (Women, 1200 kcal)', 'goal' => 'weight_loss', 'diet_preference' => 'veg', 'duration_weeks' => 8,
            'summary' => 'Gentle deficit for women and smaller-framed members. Re-check weight every 2 weeks.',
            'days' => [['diet-wl-veg-1200', 'Daily menu']]],
        ['key' => 'prog-diet-wl-egg', 'title' => 'Weight Loss — Eggetarian', 'goal' => 'weight_loss', 'diet_preference' => 'eggetarian', 'duration_weeks' => 12,
            'summary' => '~1500 kcal a day with eggs for extra protein, alternating with a vegetarian day.',
            'days' => [['diet-wl-egg-1500', 'Menu A — with eggs'], ['diet-wl-veg-1500', 'Menu B — vegetarian'], ['diet-wl-egg-1500', 'Menu A — with eggs'], ['diet-wl-veg-1500', 'Menu B — vegetarian'], ['diet-wl-egg-1500', 'Menu A — with eggs'], ['diet-south-1500', 'Menu C — South Indian'], ['diet-wl-egg-1500', 'Menu A — with eggs']]],
        ['key' => 'prog-diet-wl-nonveg', 'title' => 'Weight Loss — Non-Vegetarian', 'goal' => 'weight_loss', 'diet_preference' => 'non_veg', 'duration_weeks' => 12,
            'summary' => '~1500 kcal, ~100 g protein from chicken, fish and eggs, with an eggetarian day for variety.',
            'days' => [['diet-wl-nonveg-1500', 'Menu A — chicken & fish'], ['diet-wl-egg-1500', 'Menu B — eggs'], ['diet-wl-nonveg-1500', 'Menu A — chicken & fish'], ['diet-wl-egg-1500', 'Menu B — eggs'], ['diet-wl-nonveg-1500', 'Menu A — chicken & fish'], ['diet-wl-egg-1500', 'Menu B — eggs'], ['diet-wl-nonveg-1500', 'Menu A — chicken & fish']]],
        ['key' => 'prog-diet-wl-nonveg-men', 'title' => 'Weight Loss — Non-Vegetarian (Active Men, 1800 kcal)', 'goal' => 'weight_loss', 'diet_preference' => 'non_veg', 'duration_weeks' => 12,
            'summary' => 'For men lifting 4× a week: ~130 g protein to hold muscle while losing fat.',
            'days' => [['diet-wl-nonveg-1800', 'Daily menu']]],
        ['key' => 'prog-diet-wl-vegan', 'title' => 'Weight Loss — Vegan', 'goal' => 'weight_loss', 'diet_preference' => 'vegan', 'duration_weeks' => 12,
            'summary' => '~1400 kcal, fully plant-based with ~75 g protein.',
            'days' => [['diet-wl-vegan-1400', 'Daily menu']]],

        // ---- Muscle / weight gain ------------------------------------------------------------
        ['key' => 'prog-diet-mg-veg', 'title' => 'Muscle Gain — Vegetarian', 'goal' => 'muscle_gain', 'diet_preference' => 'veg', 'duration_weeks' => 12,
            'summary' => '~2600 kcal surplus, ~110 g protein from dairy, paneer and pulses.',
            'days' => [['diet-mg-veg-2600', 'Daily menu']]],
        ['key' => 'prog-diet-mg-egg', 'title' => 'Muscle Gain — Eggetarian', 'goal' => 'muscle_gain', 'diet_preference' => 'eggetarian', 'duration_weeks' => 12,
            'summary' => '~2600 kcal with eggs daily, ~130 g protein, alternating with the vegetarian bulk menu.',
            'days' => [['diet-mg-egg-2600', 'Menu A — with eggs'], ['diet-mg-veg-2600', 'Menu B — vegetarian'], ['diet-mg-egg-2600', 'Menu A — with eggs'], ['diet-mg-veg-2600', 'Menu B — vegetarian'], ['diet-mg-egg-2600', 'Menu A — with eggs'], ['diet-mg-veg-2600', 'Menu B — vegetarian'], ['diet-mg-egg-2600', 'Menu A — with eggs']]],
        ['key' => 'prog-diet-mg-nonveg', 'title' => 'Muscle Gain — Non-Vegetarian (Lean Bulk)', 'goal' => 'muscle_gain', 'diet_preference' => 'non_veg', 'duration_weeks' => 12,
            'summary' => '~2750 kcal, ~160 g protein, alternating with an eggetarian day.',
            'days' => [['diet-mg-nonveg-2750', 'Menu A — chicken & fish'], ['diet-mg-egg-2600', 'Menu B — eggs'], ['diet-mg-nonveg-2750', 'Menu A — chicken & fish'], ['diet-mg-egg-2600', 'Menu B — eggs'], ['diet-mg-nonveg-2750', 'Menu A — chicken & fish'], ['diet-mg-egg-2600', 'Menu B — eggs'], ['diet-mg-nonveg-2750', 'Menu A — chicken & fish']]],
        ['key' => 'prog-diet-mg-vegan', 'title' => 'Muscle Gain — Vegan', 'goal' => 'muscle_gain', 'diet_preference' => 'vegan', 'duration_weeks' => 12,
            'summary' => '~2500 kcal plant-based surplus, ~120 g protein from soya, tofu and dals.',
            'days' => [['diet-mg-vegan-2500', 'Daily menu']]],

        // ---- Lean & toning -------------------------------------------------------------------------
        ['key' => 'prog-diet-lean-veg', 'title' => 'Lean & Toning — Vegetarian', 'goal' => 'lean', 'diet_preference' => 'veg', 'duration_weeks' => 12,
            'summary' => 'Around maintenance with high protein, alternating with the balanced Indian plate.',
            'days' => [['diet-lean-veg-2000', 'Menu A — high protein'], ['diet-bal-1900', 'Menu B — balanced plate'], ['diet-lean-veg-2000', 'Menu A — high protein'], ['diet-bal-1900', 'Menu B — balanced plate'], ['diet-lean-veg-2000', 'Menu A — high protein'], ['diet-bal-1900', 'Menu B — balanced plate'], ['diet-lean-veg-2000', 'Menu A — high protein']]],
        ['key' => 'prog-diet-lean-egg', 'title' => 'Lean & Toning — Eggetarian', 'goal' => 'lean', 'diet_preference' => 'eggetarian', 'duration_weeks' => 12,
            'summary' => '~2000 kcal, ~115 g protein with eggs, alternating with the vegetarian lean menu.',
            'days' => [['diet-lean-egg-2000', 'Menu A — with eggs'], ['diet-lean-veg-2000', 'Menu B — vegetarian'], ['diet-lean-egg-2000', 'Menu A — with eggs'], ['diet-lean-veg-2000', 'Menu B — vegetarian'], ['diet-lean-egg-2000', 'Menu A — with eggs'], ['diet-lean-veg-2000', 'Menu B — vegetarian'], ['diet-lean-egg-2000', 'Menu A — with eggs']]],
        ['key' => 'prog-diet-lean-nonveg', 'title' => 'Lean & Toning — Non-Vegetarian', 'goal' => 'lean', 'diet_preference' => 'non_veg', 'duration_weeks' => 12,
            'summary' => '~2100 kcal, ~140 g protein, alternating with an eggetarian day.',
            'days' => [['diet-lean-nonveg-2100', 'Menu A — chicken & fish'], ['diet-lean-egg-2000', 'Menu B — eggs'], ['diet-lean-nonveg-2100', 'Menu A — chicken & fish'], ['diet-lean-egg-2000', 'Menu B — eggs'], ['diet-lean-nonveg-2100', 'Menu A — chicken & fish'], ['diet-lean-egg-2000', 'Menu B — eggs'], ['diet-lean-nonveg-2100', 'Menu A — chicken & fish']]],
        ['key' => 'prog-diet-lean-vegan', 'title' => 'Lean & Toning — Vegan', 'goal' => 'lean', 'diet_preference' => 'vegan', 'duration_weeks' => 12,
            'summary' => '~2000 kcal plant-based, ~100 g protein, alternating with the vegan high-protein menu.',
            'days' => [['diet-lean-vegan-2000', 'Menu A'], ['diet-vegan-1800', 'Menu B — high protein'], ['diet-lean-vegan-2000', 'Menu A'], ['diet-vegan-1800', 'Menu B — high protein'], ['diet-lean-vegan-2000', 'Menu A'], ['diet-vegan-1800', 'Menu B — high protein'], ['diet-lean-vegan-2000', 'Menu A']]],

        // ---- Maintenance & health -------------------------------------------------------------------
        ['key' => 'prog-diet-maintain-veg', 'title' => 'Maintenance — Balanced Indian Plate', 'goal' => 'maintenance', 'diet_preference' => 'veg', 'duration_weeks' => 12,
            'summary' => 'Stay at your weight with the ICMR-NIN balanced plate, with a South Indian day for variety.',
            'days' => [['diet-bal-1900', 'Balanced plate'], ['diet-bal-1900', 'Balanced plate'], ['diet-bal-1900', 'Balanced plate'], ['diet-lean-veg-2000', 'High-protein day'], ['diet-bal-1900', 'Balanced plate'], ['diet-bal-1900', 'Balanced plate'], ['diet-lean-veg-2000', 'High-protein day']]],
        ['key' => 'prog-diet-diabetes-veg', 'title' => 'Diabetes-friendly — Vegetarian', 'goal' => 'diabetes', 'diet_preference' => 'veg', 'duration_weeks' => 12,
            'summary' => 'Low-GI, smaller regular meals. Follow your doctor\'s advice on medication.',
            'days' => [['diet-dm-veg-1600', 'Daily menu']]],
        ['key' => 'prog-diet-pcos-veg', 'title' => 'PCOS-friendly — Vegetarian', 'goal' => 'pcos', 'diet_preference' => 'veg', 'duration_weeks' => 12,
            'summary' => 'Low-GI, high-protein, high-fibre meals. Combine with strength training 3× a week.',
            'days' => [['diet-pcos-1500', 'Daily menu']]],
    ],
];
