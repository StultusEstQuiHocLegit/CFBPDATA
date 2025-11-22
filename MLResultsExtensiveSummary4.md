# General Summary

Logistic regression bankruptcy classifier trained on grouped annual filings (validation year 2023, test year 2024) with inverse-frequency class weighting and isotonic calibration. Latest model snapshot: 2025-10-29T12:34:42+01:00.

    Optimisation target: precision-recall AUC (PR AUC = 0.6368, ROC AUC = 0.5534, Brier score = 0.2412).
    Calibration thresholds (isotonic): probability grid [0, 0.4308, 0.5, 0.5, 0.5, 0.5, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 0.5692, 1] -> calibrated scores [0.3077, 0.3077, 0.4506, 0.5, 0.5, 0.569, 0.6286, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1].
    Primary decision point (threshold 1) yields TP=10, FP=1, TN=641, FN=929 (precision 0.9091, recall 0.0106, F1 0.0211). Strict recall 0.8 uses threshold 0.4469 with TP=749, FP=506, TN=136, FN=190 (precision 0.5968, recall 0.7977, F1 0.6828).
    Calibration reliability (test deciles): [0.31-0.43]=0.39->0.56, [0.43-0.45]=0.44->0.6, [0.45-0.45]=0.45->0.58, [0.45-0.49]=0.45->0.23, [0.5-0.57]=0.56->0.67.
    L2 regularisation λ=0.01, 400 gradient-descent epochs, learning rate 0.05. Bias = 0.










# Training metadata & feature order

{
    "timestamp": "2025-10-29T12:34:42+01:00",
    "config": {
        "paths": {
            "bankrupt_csv": "data/raw/financials.csv",
            "solvent_csv": "data/raw/financials_solvent.csv"
        },
        "schema": {
            "id": "idpk",
            "time": "year"
        },
        "split": {
            "test_year": 2024,
            "valid_year": 2023,
            "group_by_company": true
        },
        "preprocess": {
            "winsorize": {
                "lower": 0.02,
                "upper": 0.98
            },
            "impute_strategy": "median",
            "scale": "robust"
        },
        "class_weighting": "inverse_frequency",
        "model": {
            "type": "logistic_regression",
            "l2": 0.1,
            "l2_grid": [
                0.01,
                0.05,
                0.1,
                0.5,
                1
            ],
            "iterations": 400,
            "learning_rate": 0.05,
            "max_grad_norm": 5,
            "min_probability_bins": 8,
            "l2_bin_retry_factor": 5,
            "max_bin_retries": 5,
            "seed": 42,
            "gradient_boosting": {
                "num_trees": 50,
                "learning_rate": 0.1,
                "max_depth": 1
            }
        },
        "calibration": "isotonic",
        "thresholds": {
            "optimize_for": "pr_auc",
            "strict_recall_at": 0.8
        }
    },
    "feature_names": [
        "current_ratio",
        "debt_to_assets",
        "roa",
        "current_ratio_tren",
        "debt_to_assets_tren",
        "roa_tren",
        "current_ratio_vola",
        "debt_to_assets_vola",
        "roa_vola",
        "ln_assets",
        "EBIT_InterestExpense",
        "CFO_Liabilities",
        "leverage_profitability",
        "liquidity_cashflow",
        "size_profitability",
        "AltmanZPrime",
        "company_id_missing",
        "fiscal_year_missing",
        "label_missing",
        "current_ratio_missing",
        "debt_to_assets_missing",
        "roa_missing",
        "current_ratio_tren_missing",
        "debt_to_assets_tren_missing",
        "roa_tren_missing",
        "current_ratio_vola_missing",
        "debt_to_assets_vola_missing",
        "roa_vola_missing",
        "ln_assets_missing",
        "EBIT_InterestExpense_missing",
        "CFO_Liabilities_missing",
        "leverage_profitability_missing",
        "liquidity_cashflow_missing",
        "size_profitability_missing",
        "AltmanZPrime_missing"
    ],
    "hyperparameters": {
        "model_type": "logistic_regression",
        "selected_l2": 0.01
    },
    "calibration": {
        "class": "App\\ML\\Calibrator\\Isotonic",
        "type": "isotonic"
    },
    "selected_l2": 0.01
}










# Evaluation metrics (validation/test)

{
    "pr_auc": 0.6368473914000622,
    "roc_auc": 0.5534322653847301,
    "brier": 0.24118079049036875,
    "thresholds": {
        "primary": 1,
        "f1_max": 0.307692312480907,
        "recall_target": 0.4469305615016641,
        "best": 1,
        "recall80": 0.4469305615016641
    },
    "operating_points": {
        "validation": {
            "primary": {
                "threshold": 1,
                "precision": 1,
                "recall": 0.019230769230769232,
                "f1": 0.03773584905660378,
                "support": {
                    "tp": 18,
                    "fp": 0,
                    "tn": 733,
                    "fn": 918
                },
                "threshold_index": 1635
            },
            "f1_max": {
                "threshold": 0.307692312480907,
                "precision": 0.5628019323671497,
                "recall": 0.9957264957264957,
                "f1": 0.7191358024691358,
                "support": {
                    "tp": 932,
                    "fp": 724,
                    "tn": 9,
                    "fn": 4
                },
                "threshold_index": 2
            },
            "recall_target": {
                "threshold": 0.4469305615016641,
                "precision": 0.5593726661687827,
                "recall": 0.8002136752136753,
                "f1": 0.6584615384615384,
                "support": {
                    "tp": 749,
                    "fp": 590,
                    "tn": 143,
                    "fn": 187
                },
                "threshold_index": 319,
                "target_recall": 0.8
            }
        },
        "test": {
            "primary": {
                "threshold": 1,
                "precision": 0.9090909090909091,
                "recall": 0.010649627263045794,
                "f1": 0.02105263157894737,
                "support": {
                    "tp": 10,
                    "fp": 1,
                    "tn": 641,
                    "fn": 929
                },
                "threshold_index": 1635
            },
            "f1_max": {
                "threshold": 0.307692312480907,
                "precision": 0.5965692503176621,
                "recall": 1,
                "f1": 0.7473139673696777,
                "support": {
                    "tp": 939,
                    "fp": 635,
                    "tn": 7,
                    "fn": 0
                },
                "threshold_index": 2
            },
            "recall_target": {
                "threshold": 0.4469305615016641,
                "precision": 0.5968127490039841,
                "recall": 0.7976570820021299,
                "f1": 0.6827711941659069,
                "support": {
                    "tp": 749,
                    "fp": 506,
                    "tn": 136,
                    "fn": 190
                },
                "threshold_index": 319,
                "target_recall": 0.8
            }
        }
    },
    "reliability": {
        "validation": [
            {
                "bin": 1,
                "lower": 0.3076923076923077,
                "upper": 0.43248836764079834,
                "count": 167,
                "avg_pred": 0.3889860596665798,
                "emp_rate": 0.5449101796407185
            },
            {
                "bin": 2,
                "lower": 0.4325238138445995,
                "upper": 0.4470052842573876,
                "count": 167,
                "avg_pred": 0.44038835139361104,
                "emp_rate": 0.5868263473053892
            },
            {
                "bin": 3,
                "lower": 0.4470445375607449,
                "upper": 0.45056720401664463,
                "count": 167,
                "avg_pred": 0.44993820957853886,
                "emp_rate": 0.4431137724550898
            },
            {
                "bin": 4,
                "lower": 0.45056720554023266,
                "upper": 0.5281868692672181,
                "count": 167,
                "avg_pred": 0.46121651002219094,
                "emp_rate": 0.25748502994011974
            },
            {
                "bin": 5,
                "lower": 0.5285309041790749,
                "upper": 0.5689655408408757,
                "count": 167,
                "avg_pred": 0.5646310619085684,
                "emp_rate": 0.6586826347305389
            },
            {
                "bin": 6,
                "lower": 0.5689655421154585,
                "upper": 0.5689714107502407,
                "count": 167,
                "avg_pred": 0.568965802735228,
                "emp_rate": 0.7245508982035929
            },
            {
                "bin": 7,
                "lower": 0.568971984532327,
                "upper": 0.5697801111144475,
                "count": 167,
                "avg_pred": 0.5692733350916775,
                "emp_rate": 0.5508982035928144
            },
            {
                "bin": 8,
                "lower": 0.5697914113748237,
                "upper": 0.5753304649687805,
                "count": 167,
                "avg_pred": 0.5718330429578663,
                "emp_rate": 0.6047904191616766
            },
            {
                "bin": 9,
                "lower": 0.5754211616587619,
                "upper": 0.6088108092693425,
                "count": 167,
                "avg_pred": 0.5871454612196992,
                "emp_rate": 0.6407185628742516
            },
            {
                "bin": 10,
                "lower": 0.6093181977968033,
                "upper": 1,
                "count": 166,
                "avg_pred": 0.6670596015639743,
                "emp_rate": 0.5963855421686747
            }
        ],
        "test": [
            {
                "bin": 1,
                "lower": 0.3076923076923077,
                "upper": 0.43322968406544926,
                "count": 159,
                "avg_pred": 0.39050175004565624,
                "emp_rate": 0.559748427672956
            },
            {
                "bin": 2,
                "lower": 0.43345660031455174,
                "upper": 0.44626192810541454,
                "count": 159,
                "avg_pred": 0.4409149329892386,
                "emp_rate": 0.5974842767295597
            },
            {
                "bin": 3,
                "lower": 0.44654390456883786,
                "upper": 0.4505670992537375,
                "count": 159,
                "avg_pred": 0.44932781164080393,
                "emp_rate": 0.5786163522012578
            },
            {
                "bin": 4,
                "lower": 0.45056710587915916,
                "upper": 0.49351184660009917,
                "count": 159,
                "avg_pred": 0.4518984057484883,
                "emp_rate": 0.23270440251572327
            },
            {
                "bin": 5,
                "lower": 0.5,
                "upper": 0.5689655313641901,
                "count": 159,
                "avg_pred": 0.557512756769172,
                "emp_rate": 0.6666666666666666
            },
            {
                "bin": 6,
                "lower": 0.5689655316546254,
                "upper": 0.5689656650879472,
                "count": 159,
                "avg_pred": 0.5689655721076904,
                "emp_rate": 0.7735849056603774
            },
            {
                "bin": 7,
                "lower": 0.5689656752540106,
                "upper": 0.5693792361092056,
                "count": 159,
                "avg_pred": 0.5690608214250465,
                "emp_rate": 0.5849056603773585
            },
            {
                "bin": 8,
                "lower": 0.5693829666513636,
                "upper": 0.5730081124888875,
                "count": 159,
                "avg_pred": 0.5708204478171145,
                "emp_rate": 0.6792452830188679
            },
            {
                "bin": 9,
                "lower": 0.5731990747888535,
                "upper": 0.6025287126229986,
                "count": 159,
                "avg_pred": 0.5834207296099579,
                "emp_rate": 0.6477987421383647
            },
            {
                "bin": 10,
                "lower": 0.6028010580847835,
                "upper": 1,
                "count": 150,
                "avg_pred": 0.6522024078399462,
                "emp_rate": 0.62
            }
        ]
    },
    "confusion_best": {
        "TP": 10,
        "FP": 1,
        "TN": 641,
        "FN": 929,
        "precision": 0.9090909090909091,
        "recall": 0.010649627263045794,
        "f1": 0.02105263157894737
    },
    "confusion_strict": {
        "TP": 749,
        "FP": 506,
        "TN": 136,
        "FN": 190,
        "precision": 0.5968127490039841,
        "recall": 0.7976570820021299,
        "f1": 0.6827711941659069
    },
    "calibration": {
        "type": "isotonic"
    },
    "model_type": "logistic_regression",
    "selected_l2": 0.01
}










# Calibration curve (isotonic)

{
    "thresholds": [
        0,
        0.43075758195537656,
        0.4999999808788674,
        0.499999984954255,
        0.4999999865320605,
        0.4999999984950798,
        0.5692423427392813,
        0.5692423450274778,
        0.5692423464346267,
        0.5692423470262575,
        0.5692423475811016,
        0.5692423541360303,
        0.5692423545008458,
        0.5692423581845124,
        0.5692423594174013,
        0.5692423628788746,
        0.5692423643788712,
        0.5692423654314791,
        0.5692423668139722,
        0.5692423757727713,
        0.5692423804101409,
        0.5692423857492401,
        0.56924240486913,
        0.569242410928388,
        0.5692424135093328,
        1
    ],
    "values": [
        0.3076923076923077,
        0.3076923076923077,
        0.4505672609400324,
        0.5,
        0.5,
        0.5689655172413793,
        0.6285714285714286,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1
    ]
}










# Model coefficients (bias, λ, iterations, learning rate, per-feature weights)

{
    "weights": [
        -9.727016134021613e-10,
        -2.7908145966037203e-8,
        8.344511076345544e-9,
        1.253196595417508e-9,
        -7.244580377477602e-10,
        5.261056966938169e-10,
        -7.131249159376569e-9,
        -9.837725115137688e-9,
        -9.893229458534892e-9,
        1.5108430017958753e-8,
        2.5171827301596295e-8,
        4.952397929675123e-9,
        4.871332165761192e-7,
        -2.3196376703306314e-5,
        5.218297711418939e-10,
        9.744406399273805e-9,
        0,
        0,
        0,
        -8.736155125398262e-9,
        3.4976675756232273e-9,
        -4.078358574387169e-9,
        3.8976687278614384e-8,
        4.917124224381978e-8,
        5.813651615292974e-8,
        3.8976687278614384e-8,
        4.917124224381978e-8,
        5.813651615292974e-8,
        -6.046421484400596e-10,
        -9.668396227059432e-9,
        -1.6238171891223527e-9,
        8.217392903241316e-10,
        -2.4837441718512696e-9,
        -4.078358574387169e-9,
        0
    ],
    "bias": 8.760343129509398e-22,
    "lambda": 0.01,
    "iterations": 400,
    "learningRate": 0.05,
    "maxGradNorm": 5,
    "earlyStoppingPatience": 5,
    "earlyStoppingMinDelta": 0.0001
}










# Preprocessing pipeline

Pipeline order: winsorisation -> median imputation (+missingness flags) -> robust scaling -> one-hot encoding.





# Winsorizer parameters & cutoffs

{
    "lower": 0.02,
    "upper": 0.98,
    "cutoffs": {
        "company_id": {
            "low": 202,
            "high": 10517
        },
        "fiscal_year": {
            "low": 2014,
            "high": 2022
        },
        "label": {
            "low": 0,
            "high": 1
        },
        "debt_to_assets": {
            "low": 0.03238451332770474,
            "high": 5
        },
        "roa": {
            "low": -1,
            "high": 0.18963814235529558
        },
        "ln_assets": {
            "low": 10.028444791940512,
            "high": 26.71271335121065
        },
        "EBIT_InterestExpense": {
            "low": -5,
            "high": 10
        },
        "leverage_profitability": {
            "low": -5,
            "high": 0.12144938261085439
        },
        "size_profitability": {
            "low": -5,
            "high": 3.9717908150143093
        },
        "AltmanZPrime": {
            "low": -10,
            "high": 5.2053824402748
        },
        "current_ratio": {
            "low": 0.008923825353307421,
            "high": 5
        },
        "CFO_Liabilities": {
            "low": -2,
            "high": 0.826068191627104
        },
        "liquidity_cashflow": {
            "low": -2,
            "high": 2
        },
        "current_ratio_tren": {
            "low": -1,
            "high": 1
        },
        "debt_to_assets_tren": {
            "low": -1,
            "high": 1
        },
        "roa_tren": {
            "low": -1,
            "high": 1
        },
        "current_ratio_vola": {
            "low": 0.021410022900277048,
            "high": 2
        },
        "debt_to_assets_vola": {
            "low": 0.006335808244811281,
            "high": 2
        },
        "roa_vola": {
            "low": 0.001511420558587202,
            "high": 2
        }
    }
}





# Imputer medians & indicator mapping

{
    "medians": {
        "company_id": 5055,
        "fiscal_year": 2019,
        "label": 1,
        "current_ratio": 1.640295662856332,
        "debt_to_assets": 0.6142431242943835,
        "roa": 0.0021612117453119477,
        "current_ratio_tren": 0.05781701782186732,
        "debt_to_assets_tren": 0.002677596022704648,
        "roa_tren": 0.00388393918475455,
        "current_ratio_vola": 0.8641321415010376,
        "debt_to_assets_vola": 0.20812975071859807,
        "roa_vola": 0.05138404172402002,
        "ln_assets": 20.360486585288182,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "leverage_profitability": 4.220714343349934e-6,
        "liquidity_cashflow": -5.047645662640472e-7,
        "size_profitability": 0.04536548382817452,
        "AltmanZPrime": 0.15447499803649
    },
    "indicatorNames": {
        "company_id": "company_id_missing",
        "fiscal_year": "fiscal_year_missing",
        "label": "label_missing",
        "current_ratio": "current_ratio_missing",
        "debt_to_assets": "debt_to_assets_missing",
        "roa": "roa_missing",
        "current_ratio_tren": "current_ratio_tren_missing",
        "debt_to_assets_tren": "debt_to_assets_tren_missing",
        "roa_tren": "roa_tren_missing",
        "current_ratio_vola": "current_ratio_vola_missing",
        "debt_to_assets_vola": "debt_to_assets_vola_missing",
        "roa_vola": "roa_vola_missing",
        "ln_assets": "ln_assets_missing",
        "EBIT_InterestExpense": "EBIT_InterestExpense_missing",
        "CFO_Liabilities": "CFO_Liabilities_missing",
        "leverage_profitability": "leverage_profitability_missing",
        "liquidity_cashflow": "liquidity_cashflow_missing",
        "size_profitability": "size_profitability_missing",
        "AltmanZPrime": "AltmanZPrime_missing"
    }
}





# Robust scaler medians & IQRs

{
    "medians": {
        "company_id": 5055,
        "fiscal_year": 2019,
        "label": 1,
        "current_ratio": 1.640295662856332,
        "debt_to_assets": 0.6142431242943835,
        "roa": 0.0021612117453119477,
        "current_ratio_tren": 0.05781701782186732,
        "debt_to_assets_tren": 0.002677596022704648,
        "roa_tren": 0.00388393918475455,
        "current_ratio_vola": 0.8641321415010376,
        "debt_to_assets_vola": 0.20812975071859807,
        "roa_vola": 0.05138404172402002,
        "ln_assets": 20.360486585288182,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "leverage_profitability": 4.220714343349934e-6,
        "liquidity_cashflow": -5.047645662640472e-7,
        "size_profitability": 0.04536548382817452,
        "AltmanZPrime": 0.15447499803649,
        "company_id_missing": 0,
        "fiscal_year_missing": 0,
        "label_missing": 0,
        "current_ratio_missing": 0,
        "debt_to_assets_missing": 0,
        "roa_missing": 0,
        "current_ratio_tren_missing": 1,
        "debt_to_assets_tren_missing": 1,
        "roa_tren_missing": 1,
        "current_ratio_vola_missing": 1,
        "debt_to_assets_vola_missing": 1,
        "roa_vola_missing": 1,
        "ln_assets_missing": 0,
        "EBIT_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 0,
        "leverage_profitability_missing": 0,
        "liquidity_cashflow_missing": 0,
        "size_profitability_missing": 0,
        "AltmanZPrime_missing": 0
    },
    "iqr": {
        "company_id": 5100,
        "fiscal_year": 5,
        "label": 1,
        "current_ratio": 1.3827459050619193,
        "debt_to_assets": 0.42126595758989516,
        "roa": 0.08231505469191362,
        "current_ratio_tren": 1,
        "debt_to_assets_tren": 1,
        "roa_tren": 1,
        "current_ratio_vola": 1,
        "debt_to_assets_vola": 1,
        "roa_vola": 1,
        "ln_assets": 4.702217012571836,
        "EBIT_InterestExpense": 1,
        "CFO_Liabilities": 0.07649589798301172,
        "leverage_profitability": 0.020329325371956163,
        "liquidity_cashflow": 0.00016642487687652138,
        "size_profitability": 1.5849150765755877,
        "AltmanZPrime": 1.3500636085814,
        "company_id_missing": 1,
        "fiscal_year_missing": 1,
        "label_missing": 1,
        "current_ratio_missing": 1,
        "debt_to_assets_missing": 1,
        "roa_missing": 1,
        "current_ratio_tren_missing": 1,
        "debt_to_assets_tren_missing": 1,
        "roa_tren_missing": 1,
        "current_ratio_vola_missing": 1,
        "debt_to_assets_vola_missing": 1,
        "roa_vola_missing": 1,
        "ln_assets_missing": 1,
        "EBIT_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 1,
        "leverage_profitability_missing": 1,
        "liquidity_cashflow_missing": 1,
        "size_profitability_missing": 1,
        "AltmanZPrime_missing": 1
    }
}





# One-hot encoder categories

{
    "categorical": [
        "EntityFilerCategory"
    ],
    "mapping": {
        "EntityFilerCategory": []
    }
}