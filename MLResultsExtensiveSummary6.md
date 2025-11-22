# General Summary

Logistic regression bankruptcy classifier trained on grouped annual filings (validation year 2023, test year 2024) with inverse-frequency class weighting and isotonic calibration. Latest model snapshot: 2025-11-22T14:27:11+01:00.

    Optimisation target: precision-recall AUC (PR AUC = 0.6672, ROC AUC = 0.5845, Brier score = 0.2295).
    Calibration thresholds (isotonic): probability grid [0, 0.4236, 0.4999, 0.4999, 0.4999, 0.5761, 0.5762, 0.5764, 0.5764, 0.5764, 0.5764, 0.5764, 0.5764, 0.5764, 0.5765, 0.5766, 0.5766, 0.5767, 1] -> calibrated scores [0.0833, 0.0833, 0.3924, 0.4615, 0.6667, 0.7139, 0.8333, 0.95, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1].
    Primary decision point (threshold N/A) yields TP=9, FP=0, TN=642, FN=930 (precision 1, recall 0.0096, F1 0.019). Strict recall 0.8 has no available threshold with TP=755, FP=512, TN=130, FN=184 (precision 0.5959, recall 0.804, F1 0.6845).
    Calibration reliability (test deciles): [0.08-0.32]=0.24->0.54, [0.32-0.37]=0.35->0.64, [0.37-0.39]=0.38->0.62, [0.39-0.39]=0.39->0.31, [0.39-0.39]=0.39->0.09.
    L2 regularisation λ=0.01, 400 gradient-descent epochs, learning rate 0.05. Bias = 0.










# Training metadata & feature order

{
    "timestamp": "2025-11-22T14:27:11+01:00",
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
            "logistic_regression": {
                "iterations": 400,
                "learning_rate": 0.05,
                "max_grad_norm": 5,
                "min_probability_bins": 8,
                "l2_bin_retry_factor": 5,
                "max_bin_retries": 5,
                "seed": 42,
                "l2": 0.1,
                "l2_grid": [
                    0.01,
                    0.05,
                    0.1,
                    0.5,
                    1
                ]
            },
            "random_forest": {
                "num_trees": 100,
                "num_trees_grid": [
                    50,
                    100,
                    200
                ],
                "max_depth": 5,
                "max_depth_grid": [
                    3,
                    5,
                    7
                ],
                "min_samples_split": 2,
                "min_samples_split_grid": [
                    2,
                    5
                ],
                "subsample": 0.8,
                "feature_fraction": 0.8
            },
            "gradient_boosting": {
                "num_trees": 100,
                "num_trees_grid": [
                    50,
                    100,
                    200
                ],
                "learning_rate": 0.1,
                "learning_rate_grid": [
                    0.05,
                    0.1,
                    0.2
                ],
                "max_depth": 3,
                "max_depth_grid": [
                    2,
                    3,
                    4
                ]
            }
        },
        "calibration": "isotonic",
        "beta": {
            "learning_rate": 0.01,
            "iterations": 1000
        },
        "cross_validation": {
            "enabled": true,
            "num_folds": 3,
            "metric": "pr_auc"
        },
        "thresholds": {
            "optimize_for": "pr_auc",
            "strict_recall_at": 0.8,
            "cost_false_positive": 1,
            "cost_false_negative": 5,
            "beta": 1
        }
    },
    "feature_names": [
        "current_ratio",
        "quick_ratio",
        "debt_to_assets",
        "roa",
        "net_margin",
        "asset_turnover",
        "inventory_turnover",
        "cash_ratio",
        "operating_margin",
        "debt_to_equity",
        "current_ratio_tren",
        "quick_ratio_tren",
        "debt_to_assets_tren",
        "roa_tren",
        "net_margin_tren",
        "asset_turnover_tren",
        "inventory_turnover_tren",
        "cash_ratio_tren",
        "operating_margin_tren",
        "debt_to_equity_tren",
        "EBIT_InterestExpense_tren",
        "CFO_Liabilities_tren",
        "TL_TA_tren",
        "Debt_Assets_tren",
        "WC_TA_tren",
        "EBITDA_InterestExpense_tren",
        "CFO_DebtService_tren",
        "ROA_tren",
        "OperatingMargin_tren",
        "DaysAR_tren",
        "DaysINV_tren",
        "DaysAP_tren",
        "CashConversionCycle_tren",
        "DividendOmission_tren",
        "DebtIssuanceSpike_tren",
        "DebtRepaymentSpike_tren",
        "Accruals_tren",
        "current_ratio_vola",
        "quick_ratio_vola",
        "debt_to_assets_vola",
        "roa_vola",
        "net_margin_vola",
        "asset_turnover_vola",
        "inventory_turnover_vola",
        "cash_ratio_vola",
        "operating_margin_vola",
        "debt_to_equity_vola",
        "EBIT_InterestExpense_vola",
        "CFO_Liabilities_vola",
        "TL_TA_vola",
        "Debt_Assets_vola",
        "WC_TA_vola",
        "EBITDA_InterestExpense_vola",
        "CFO_DebtService_vola",
        "ROA_vola",
        "OperatingMargin_vola",
        "DaysAR_vola",
        "DaysINV_vola",
        "DaysAP_vola",
        "CashConversionCycle_vola",
        "DividendOmission_vola",
        "DebtIssuanceSpike_vola",
        "DebtRepaymentSpike_vola",
        "Accruals_vola",
        "ln_assets",
        "EBIT_InterestExpense",
        "CFO_Liabilities",
        "TL_TA",
        "Debt_Assets",
        "WC_TA",
        "EBITDA_InterestExpense",
        "CFO_DebtService",
        "ROA",
        "OperatingMargin",
        "DaysAR",
        "DaysINV",
        "DaysAP",
        "CashConversionCycle",
        "DividendOmission",
        "DebtIssuanceSpike",
        "DebtRepaymentSpike",
        "Accruals",
        "leverage_profitability",
        "liquidity_cashflow",
        "size_profitability",
        "leverage_margin",
        "liquidity_accruals",
        "AltmanZPrime",
        "company_id_missing",
        "fiscal_year_missing",
        "label_missing",
        "current_ratio_missing",
        "quick_ratio_missing",
        "debt_to_assets_missing",
        "roa_missing",
        "net_margin_missing",
        "asset_turnover_missing",
        "inventory_turnover_missing",
        "cash_ratio_missing",
        "operating_margin_missing",
        "debt_to_equity_missing",
        "current_ratio_tren_missing",
        "quick_ratio_tren_missing",
        "debt_to_assets_tren_missing",
        "roa_tren_missing",
        "net_margin_tren_missing",
        "asset_turnover_tren_missing",
        "inventory_turnover_tren_missing",
        "cash_ratio_tren_missing",
        "operating_margin_tren_missing",
        "debt_to_equity_tren_missing",
        "EBIT_InterestExpense_tren_missing",
        "CFO_Liabilities_tren_missing",
        "TL_TA_tren_missing",
        "Debt_Assets_tren_missing",
        "WC_TA_tren_missing",
        "EBITDA_InterestExpense_tren_missing",
        "CFO_DebtService_tren_missing",
        "ROA_tren_missing",
        "OperatingMargin_tren_missing",
        "DaysAR_tren_missing",
        "DaysINV_tren_missing",
        "DaysAP_tren_missing",
        "CashConversionCycle_tren_missing",
        "DividendOmission_tren_missing",
        "DebtIssuanceSpike_tren_missing",
        "DebtRepaymentSpike_tren_missing",
        "Accruals_tren_missing",
        "current_ratio_vola_missing",
        "quick_ratio_vola_missing",
        "debt_to_assets_vola_missing",
        "roa_vola_missing",
        "net_margin_vola_missing",
        "asset_turnover_vola_missing",
        "inventory_turnover_vola_missing",
        "cash_ratio_vola_missing",
        "operating_margin_vola_missing",
        "debt_to_equity_vola_missing",
        "EBIT_InterestExpense_vola_missing",
        "CFO_Liabilities_vola_missing",
        "TL_TA_vola_missing",
        "Debt_Assets_vola_missing",
        "WC_TA_vola_missing",
        "EBITDA_InterestExpense_vola_missing",
        "CFO_DebtService_vola_missing",
        "ROA_vola_missing",
        "OperatingMargin_vola_missing",
        "DaysAR_vola_missing",
        "DaysINV_vola_missing",
        "DaysAP_vola_missing",
        "CashConversionCycle_vola_missing",
        "DividendOmission_vola_missing",
        "DebtIssuanceSpike_vola_missing",
        "DebtRepaymentSpike_vola_missing",
        "Accruals_vola_missing",
        "ln_assets_missing",
        "EBIT_InterestExpense_missing",
        "CFO_Liabilities_missing",
        "TL_TA_missing",
        "Debt_Assets_missing",
        "WC_TA_missing",
        "EBITDA_InterestExpense_missing",
        "CFO_DebtService_missing",
        "ROA_missing",
        "OperatingMargin_missing",
        "DaysAR_missing",
        "DaysINV_missing",
        "DaysAP_missing",
        "CashConversionCycle_missing",
        "DividendOmission_missing",
        "DebtIssuanceSpike_missing",
        "DebtRepaymentSpike_missing",
        "Accruals_missing",
        "leverage_profitability_missing",
        "liquidity_cashflow_missing",
        "size_profitability_missing",
        "leverage_margin_missing",
        "liquidity_accruals_missing",
        "AltmanZPrime_missing"
    ],
    "hyperparameters": {
        "model_type": "logistic_regression",
        "selected_l2": 0.01,
        "cv_metric": 0.5936402941297606,
        "cv_per_fold": [
            0.6001564926906379,
            0.591713464219044,
            0.5890509254796
        ],
        "cv_folds": 3
    },
    "calibration": {
        "class": "App\\ML\\Calibrator\\Isotonic",
        "type": "isotonic"
    },
    "selected_l2": 0.01
}










# Evaluation metrics (validation/test)

{
    "pr_auc": 0.6672207153994265,
    "roc_auc": 0.5845334899259826,
    "brier": 0.2294654577501893,
    "thresholds": {
        "primary": {
            "threshold": 1,
            "expected_cost": 926,
            "f_beta": 0.021141649048625793
        },
        "f1_max": {
            "threshold": 0.08344516748206605,
            "expected_cost": 723,
            "f_beta": 0.7211723871962977
        },
        "recall_target": {
            "threshold": 0.37103409916448743,
            "target_recall": 0.8,
            "expected_cost": 786,
            "f_beta": 0.6558669001751314
        },
        "best": 1,
        "recall80": 0.37103409916448743
    },
    "operating_points": {
        "validation": {
            "primary": {
                "threshold": 1,
                "precision": 1,
                "recall": 0.010683760683760684,
                "f1": 0.021141649048625793,
                "expected_cost": 926,
                "f_beta": 0.021141649048625793,
                "support": {
                    "tp": 10,
                    "fp": 0,
                    "tn": 733,
                    "fn": 926
                },
                "threshold_index": 1649
            },
            "f1_max": {
                "threshold": 0.08344516748206605,
                "precision": 0.5642727821363911,
                "recall": 0.9989316239316239,
                "f1": 0.7211723871962977,
                "expected_cost": 723,
                "f_beta": 0.7211723871962977,
                "support": {
                    "tp": 935,
                    "fp": 722,
                    "tn": 11,
                    "fn": 1
                },
                "threshold_index": 2
            },
            "recall_target": {
                "threshold": 0.37103409916448743,
                "precision": 0.5556379821958457,
                "recall": 0.8002136752136753,
                "f1": 0.6558669001751314,
                "expected_cost": 786,
                "f_beta": 0.6558669001751314,
                "support": {
                    "tp": 749,
                    "fp": 599,
                    "tn": 134,
                    "fn": 187
                },
                "threshold_index": 311,
                "target_recall": 0.8
            }
        },
        "test": {
            "primary": {
                "threshold": 1,
                "precision": 1,
                "recall": 0.009584664536741214,
                "f1": 0.0189873417721519,
                "support": {
                    "tp": 9,
                    "fp": 0,
                    "tn": 642,
                    "fn": 930
                },
                "expected_cost": 930,
                "f_beta": 0.0189873417721519,
                "threshold_index": 1649
            },
            "f1_max": {
                "threshold": 0.08344516748206605,
                "precision": 0.5971956660293181,
                "recall": 0.9978700745473909,
                "f1": 0.7472089314194578,
                "support": {
                    "tp": 937,
                    "fp": 632,
                    "tn": 10,
                    "fn": 2
                },
                "expected_cost": 634,
                "f_beta": 0.7472089314194578,
                "threshold_index": 2
            },
            "recall_target": {
                "threshold": 0.37103409916448743,
                "precision": 0.595895816890292,
                "recall": 0.8040468583599574,
                "f1": 0.6844968268359021,
                "support": {
                    "tp": 755,
                    "fp": 512,
                    "tn": 130,
                    "fn": 184
                },
                "expected_cost": 696,
                "f_beta": 0.6844968268359021,
                "threshold_index": 311,
                "target_recall": 0.8
            }
        }
    },
    "reliability": {
        "validation": [
            {
                "bin": 1,
                "lower": 0.08333333333333333,
                "upper": 0.3230898843009814,
                "count": 167,
                "avg_pred": 0.23533892108915536,
                "emp_rate": 0.562874251497006
            },
            {
                "bin": 2,
                "lower": 0.3236342288479138,
                "upper": 0.37191501541978655,
                "count": 167,
                "avg_pred": 0.3539629555003594,
                "emp_rate": 0.592814371257485
            },
            {
                "bin": 3,
                "lower": 0.3720421407090645,
                "upper": 0.39046965952884116,
                "count": 167,
                "avg_pred": 0.3820725501708733,
                "emp_rate": 0.5449101796407185
            },
            {
                "bin": 4,
                "lower": 0.3905006062137306,
                "upper": 0.39177370560035546,
                "count": 167,
                "avg_pred": 0.3913993177245974,
                "emp_rate": 0.08982035928143713
            },
            {
                "bin": 5,
                "lower": 0.39177532118983766,
                "upper": 0.6666781826198853,
                "count": 167,
                "avg_pred": 0.4284379259358229,
                "emp_rate": 0.20958083832335328
            },
            {
                "bin": 6,
                "lower": 0.6666783754070469,
                "upper": 0.6667246457283593,
                "count": 167,
                "avg_pred": 0.6667061537062188,
                "emp_rate": 0.8982035928143712
            },
            {
                "bin": 7,
                "lower": 0.6667253466401205,
                "upper": 0.6669043256876757,
                "count": 167,
                "avg_pred": 0.6667792928035052,
                "emp_rate": 0.8562874251497006
            },
            {
                "bin": 8,
                "lower": 0.6669078417504438,
                "upper": 0.6708738849833062,
                "count": 167,
                "avg_pred": 0.6681099513798685,
                "emp_rate": 0.6167664670658682
            },
            {
                "bin": 9,
                "lower": 0.670899358599482,
                "upper": 0.7026765232231456,
                "count": 167,
                "avg_pred": 0.6814069517580574,
                "emp_rate": 0.6227544910179641
            },
            {
                "bin": 10,
                "lower": 0.7029716983841738,
                "upper": 1,
                "count": 166,
                "avg_pred": 0.7531477763551309,
                "emp_rate": 0.6144578313253012
            }
        ],
        "test": [
            {
                "bin": 1,
                "lower": 0.08333333333333333,
                "upper": 0.3201389837778753,
                "count": 159,
                "avg_pred": 0.23560371317518522,
                "emp_rate": 0.5408805031446541
            },
            {
                "bin": 2,
                "lower": 0.32146316323186347,
                "upper": 0.3721398163535228,
                "count": 159,
                "avg_pred": 0.3529952375024848,
                "emp_rate": 0.6415094339622641
            },
            {
                "bin": 3,
                "lower": 0.37216355110639165,
                "upper": 0.3876361557044171,
                "count": 159,
                "avg_pred": 0.380787303891027,
                "emp_rate": 0.6226415094339622
            },
            {
                "bin": 4,
                "lower": 0.38773557480665594,
                "upper": 0.39156639187418785,
                "count": 159,
                "avg_pred": 0.3907020178603921,
                "emp_rate": 0.31446540880503143
            },
            {
                "bin": 5,
                "lower": 0.39156917191525975,
                "upper": 0.39224645989733026,
                "count": 159,
                "avg_pred": 0.3918981855947959,
                "emp_rate": 0.09433962264150944
            },
            {
                "bin": 6,
                "lower": 0.39225954206665026,
                "upper": 0.6667142965952827,
                "count": 159,
                "avg_pred": 0.6086740036337529,
                "emp_rate": 0.7672955974842768
            },
            {
                "bin": 7,
                "lower": 0.6667143795073666,
                "upper": 0.6667748793069043,
                "count": 159,
                "avg_pred": 0.6667361244234947,
                "emp_rate": 0.9371069182389937
            },
            {
                "bin": 8,
                "lower": 0.6667751903070781,
                "upper": 0.6698088658194173,
                "count": 159,
                "avg_pred": 0.6676367679611186,
                "emp_rate": 0.779874213836478
            },
            {
                "bin": 9,
                "lower": 0.6698462763213922,
                "upper": 0.6928988833426205,
                "count": 159,
                "avg_pred": 0.677779108043657,
                "emp_rate": 0.6037735849056604
            },
            {
                "bin": 10,
                "lower": 0.6943211731814756,
                "upper": 1,
                "count": 150,
                "avg_pred": 0.7630311439058488,
                "emp_rate": 0.64
            }
        ]
    },
    "confusion_best": {
        "TP": 9,
        "FP": 0,
        "TN": 642,
        "FN": 930,
        "precision": 1,
        "recall": 0.009584664536741214,
        "f1": 0.0189873417721519
    },
    "confusion_strict": {
        "TP": 755,
        "FP": 512,
        "TN": 130,
        "FN": 184,
        "precision": 0.595895816890292,
        "recall": 0.8040468583599574,
        "f1": 0.6844968268359021
    },
    "calibration": {
        "type": "isotonic"
    },
    "hyperparameters": {
        "model_type": "logistic_regression",
        "selected_l2": 0.01,
        "cv_metric": 0.5936402941297606,
        "cv_per_fold": [
            0.6001564926906379,
            0.591713464219044,
            0.5890509254796
        ],
        "cv_folds": 3
    },
    "model_type": "logistic_regression",
    "selected_l2": 0.01,
    "cv_metric": 0.5936402941297606,
    "cv_per_fold": [
        0.6001564926906379,
        0.591713464219044,
        0.5890509254796
    ],
    "cv_folds": 3
}










# Calibration curve (isotonic)

{
    "thresholds": [
        0,
        0.42358712324288944,
        0.4998510534306939,
        0.49988858806275027,
        0.4999255711571428,
        0.5760992941702548,
        0.5761916720528202,
        0.5763830565128989,
        0.5764068989081719,
        0.5764094601045588,
        0.5764301382305949,
        0.576430260892603,
        0.5764345805356669,
        0.5764359692120575,
        0.5764525264711324,
        0.5766139379247112,
        0.5766470518293358,
        0.5767027144867652,
        1
    ],
    "values": [
        0.08333333333333333,
        0.08333333333333333,
        0.3923566878980892,
        0.46153846153846156,
        0.6666666666666666,
        0.7139334155363748,
        0.8333333333333334,
        0.95,
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
        -5.407684944373358e-6,
        -3.9371946565116285e-6,
        -3.6340283860536254e-5,
        -2.157495330941411e-6,
        -1.1814746022415827e-6,
        -3.779914017264129e-8,
        2.2750248828589398e-6,
        -1.1787107920333673e-5,
        -2.866410991016397e-7,
        -1.298661444535422e-5,
        1.3861144921893155e-6,
        1.1039333404544162e-6,
        -6.084589158162219e-7,
        -9.435930003163028e-7,
        -6.28798361873984e-8,
        9.339302336475534e-8,
        -1.3325310418358755e-7,
        -5.339174252615364e-7,
        -4.689072466343289e-10,
        -1.1295219352686188e-5,
        2.882511727204409e-6,
        -1.7068412011120285e-6,
        -6.084589158162219e-7,
        7.991473542509217e-8,
        1.7674426072014e-7,
        2.438848869103051e-6,
        -2.7788810011159474e-5,
        -9.435930003163028e-7,
        -4.689072466343289e-10,
        -1.4718954098633998e-6,
        1.0092749469423682e-6,
        -7.67353643612844e-7,
        0,
        0,
        0,
        0,
        -3.9667944831587404e-8,
        -9.846654411154816e-6,
        -3.975051444036098e-6,
        -1.3080240318679472e-5,
        -1.5879451147088988e-5,
        -6.520110552498633e-7,
        -3.1106045166637567e-7,
        -2.2813676826088478e-7,
        -1.5018464484294959e-5,
        -5.90621643713042e-7,
        2.389890824075526e-6,
        4.237462063613152e-6,
        -1.1547545620168814e-5,
        -1.3080240318679472e-5,
        -8.288146035851426e-7,
        -1.0584254783840485e-5,
        8.719619945820627e-7,
        7.944828159526044e-6,
        -1.5879451147088988e-5,
        -5.90621643713042e-7,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        -1.1990522293719745e-5,
        2.3626397140985835e-5,
        2.4873421519664182e-5,
        1.5550858621591485e-5,
        -3.6340283860536254e-5,
        2.84545534349335e-6,
        1.3128226212156839e-5,
        -1.2973685881992275e-5,
        -6.709322491663375e-8,
        -2.157495330941411e-6,
        -2.866410991016397e-7,
        0.0004881258518138492,
        -0.00017090256572068244,
        -2.173464605545158e-5,
        0.00016115013872258077,
        -1.1606317464418359e-5,
        0,
        0,
        -5.481166346654354e-6,
        0.0004925270753378404,
        -4.317412887267234e-5,
        -6.426754450904695e-6,
        -2.4329112990214554e-6,
        -4.5743236684945235e-5,
        1.695554808480991e-5,
        0,
        0,
        0,
        -1.1656919678493581e-5,
        -1.337355607055451e-5,
        4.6408093908001845e-6,
        -5.436056690459541e-6,
        -7.952532538674167e-6,
        -8.25510618179834e-6,
        -5.296526650024457e-7,
        -1.4383875299023545e-5,
        -6.948005383142722e-6,
        4.86439290212688e-6,
        5.230977914398059e-5,
        1.3176600711200753e-5,
        6.597380434573466e-5,
        7.79045560655871e-5,
        2.335666584183383e-6,
        2.9276170586881046e-6,
        1.0330445265139784e-6,
        4.491527178183459e-5,
        2.479441503901433e-6,
        6.62526113176674e-5,
        1.98397484680694e-5,
        5.182436309797206e-5,
        6.597380434573466e-5,
        1.1305819458124422e-5,
        5.165731764577645e-5,
        3.300973130944862e-6,
        3.0405069722978528e-5,
        7.79045560655871e-5,
        2.479441503901433e-6,
        1.4862388720899863e-6,
        1.0330445265139784e-6,
        8.92642622775787e-7,
        8.491592411259299e-8,
        9.633172218681925e-5,
        9.633172218681925e-5,
        9.633172218681925e-5,
        6.532655117181989e-5,
        5.230977914398059e-5,
        1.3176600711200753e-5,
        6.597380434573466e-5,
        7.79045560655871e-5,
        2.335666584183383e-6,
        2.9276170586881046e-6,
        1.0330445265139784e-6,
        4.491527178183459e-5,
        2.479441503901433e-6,
        6.62526113176674e-5,
        1.98397484680694e-5,
        5.182436309797206e-5,
        6.597380434573466e-5,
        1.1305819458124422e-5,
        5.165731764577645e-5,
        3.300973130944862e-6,
        3.0405069722978528e-5,
        7.79045560655871e-5,
        2.479441503901433e-6,
        1.4862388720899863e-6,
        1.0330445265139784e-6,
        8.92642622775787e-7,
        8.491592411259299e-8,
        9.633172218681925e-5,
        9.633172218681925e-5,
        9.633172218681925e-5,
        6.532655117181989e-5,
        -8.651627581093911e-7,
        -1.296744699206096e-5,
        -2.145832750929248e-6,
        4.6408093908001845e-6,
        -1.551047732387412e-5,
        -1.2333906775322539e-5,
        -6.0720456908848e-6,
        -1.1337626657335373e-5,
        -5.436056690459541e-6,
        -6.948005383142722e-6,
        -6.833789492173972e-6,
        -5.564788695305067e-7,
        -1.2108744233940291e-6,
        -4.7926229312419e-7,
        0,
        0,
        0,
        -5.263995796230739e-6,
        1.1067890457956777e-6,
        -3.2492783943238636e-6,
        -7.952532538674167e-6,
        -5.526909547189665e-6,
        -8.566307683467753e-6,
        0
    ],
    "bias": -9.026685920469949e-7,
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
            "low": 0.03213948114693835,
            "high": 5
        },
        "roa": {
            "low": -1,
            "high": 0.1909393396467878
        },
        "debt_to_equity": {
            "low": 0,
            "high": 10
        },
        "ln_assets": {
            "low": 10.126631103850338,
            "high": 26.721032980899015
        },
        "EBIT_InterestExpense": {
            "low": -5,
            "high": 10
        },
        "TL_TA": {
            "low": 0.03213948114693835,
            "high": 5
        },
        "ROA": {
            "low": -1,
            "high": 0.1909393396467878
        },
        "DaysAP": {
            "low": 8.38875330557096,
            "high": 400
        },
        "DividendOmission": {
            "low": 0,
            "high": 1
        },
        "DebtIssuanceSpike": {
            "low": 0,
            "high": 0
        },
        "DebtRepaymentSpike": {
            "low": 0,
            "high": 0
        },
        "leverage_profitability": {
            "low": -5,
            "high": 0.11503083552911159
        },
        "AltmanZPrime": {
            "low": -10,
            "high": 5.2053824402748
        },
        "current_ratio": {
            "low": 0.0094624748469971,
            "high": 5
        },
        "cash_ratio": {
            "low": 0,
            "high": 5
        },
        "WC_TA": {
            "low": -2,
            "high": 0.9287767521070843
        },
        "EBITDA_InterestExpense": {
            "low": -5,
            "high": 10
        },
        "CFO_DebtService": {
            "low": -2,
            "high": 2
        },
        "Accruals": {
            "low": -2,
            "high": 0.6269885442638596
        },
        "liquidity_accruals": {
            "low": -1.9372565797662398,
            "high": 1.7654031714332155
        },
        "CFO_Liabilities": {
            "low": -2,
            "high": 0.8384805718475073
        },
        "liquidity_cashflow": {
            "low": -2,
            "high": 2
        },
        "Debt_Assets": {
            "low": 0,
            "high": 1.1418273958096967
        },
        "quick_ratio": {
            "low": 0.018207640078722113,
            "high": 5
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
        "cash_ratio_tren": {
            "low": -1,
            "high": 1
        },
        "debt_to_equity_tren": {
            "low": -1,
            "high": 1
        },
        "EBIT_InterestExpense_tren": {
            "low": -1,
            "high": 1
        },
        "CFO_Liabilities_tren": {
            "low": -1,
            "high": 1
        },
        "TL_TA_tren": {
            "low": -1,
            "high": 1
        },
        "WC_TA_tren": {
            "low": -1,
            "high": 1
        },
        "CFO_DebtService_tren": {
            "low": -1,
            "high": 1
        },
        "ROA_tren": {
            "low": -1,
            "high": 1
        },
        "DividendOmission_tren": {
            "low": 0,
            "high": 0
        },
        "DebtIssuanceSpike_tren": {
            "low": 0,
            "high": 0
        },
        "DebtRepaymentSpike_tren": {
            "low": 0,
            "high": 0
        },
        "Accruals_tren": {
            "low": -1,
            "high": 1
        },
        "current_ratio_vola": {
            "low": 0.024467475352140722,
            "high": 2
        },
        "debt_to_assets_vola": {
            "low": 0.006446353989764526,
            "high": 2
        },
        "roa_vola": {
            "low": 0.001262021644921976,
            "high": 2
        },
        "cash_ratio_vola": {
            "low": 0.006076075755400923,
            "high": 2
        },
        "debt_to_equity_vola": {
            "low": 0.022595614047722423,
            "high": 2
        },
        "EBIT_InterestExpense_vola": {
            "low": 0.1760337147453932,
            "high": 2
        },
        "CFO_Liabilities_vola": {
            "low": 0.0036753103578779193,
            "high": 2
        },
        "TL_TA_vola": {
            "low": 0.006446353989764526,
            "high": 2
        },
        "WC_TA_vola": {
            "low": 0.004543619091757414,
            "high": 2
        },
        "CFO_DebtService_vola": {
            "low": 0.1460155538675622,
            "high": 2
        },
        "ROA_vola": {
            "low": 0.001262021644921976,
            "high": 2
        },
        "DividendOmission_vola": {
            "low": 0,
            "high": 0
        },
        "DebtIssuanceSpike_vola": {
            "low": 0,
            "high": 0
        },
        "DebtRepaymentSpike_vola": {
            "low": 0,
            "high": 0
        },
        "Accruals_vola": {
            "low": 0.0009980519484285334,
            "high": 2
        },
        "net_margin": {
            "low": -1,
            "high": 0.6671485017301887
        },
        "asset_turnover": {
            "low": 0,
            "high": 2.9454644358361493
        },
        "operating_margin": {
            "low": -1,
            "high": 0.8585448392554992
        },
        "OperatingMargin": {
            "low": -1,
            "high": 0.8585448392554992
        },
        "DaysAR": {
            "low": 1.5770190089968485,
            "high": 400
        },
        "size_profitability": {
            "low": -5,
            "high": 5
        },
        "inventory_turnover": {
            "low": 0.023887973640856673,
            "high": 5
        },
        "DaysINV": {
            "low": 5.174627225528467,
            "high": 400
        },
        "Debt_Assets_tren": {
            "low": -0.8851327629103689,
            "high": 1
        },
        "Debt_Assets_vola": {
            "low": 0.002264844074716582,
            "high": 2
        },
        "leverage_margin": {
            "low": -10,
            "high": 10
        },
        "CashConversionCycle": {
            "low": -400,
            "high": 400
        },
        "quick_ratio_tren": {
            "low": -1,
            "high": 1
        },
        "quick_ratio_vola": {
            "low": 0.028620773051236434,
            "high": 2
        },
        "net_margin_tren": {
            "low": -1,
            "high": 1
        },
        "asset_turnover_tren": {
            "low": -1,
            "high": 1
        },
        "operating_margin_tren": {
            "low": -1,
            "high": 1
        },
        "OperatingMargin_tren": {
            "low": -1,
            "high": 1
        },
        "DaysAR_tren": {
            "low": -1,
            "high": 1
        },
        "net_margin_vola": {
            "low": 0.0033393814469486714,
            "high": 2
        },
        "asset_turnover_vola": {
            "low": 0.009514414349858974,
            "high": 1.5806029144284204
        },
        "operating_margin_vola": {
            "low": 0.006692736336535696,
            "high": 2
        },
        "OperatingMargin_vola": {
            "low": 0.006692736336535696,
            "high": 2
        },
        "DaysAR_vola": {
            "low": 2,
            "high": 2
        },
        "inventory_turnover_tren": {
            "low": -1,
            "high": 1
        },
        "DaysINV_tren": {
            "low": -1,
            "high": 1
        },
        "DaysAP_tren": {
            "low": -1,
            "high": 1
        },
        "inventory_turnover_vola": {
            "low": 0.03277358559346748,
            "high": 2
        },
        "DaysINV_vola": {
            "low": 2,
            "high": 2
        },
        "DaysAP_vola": {
            "low": 2,
            "high": 2
        },
        "EBITDA_InterestExpense_tren": {
            "low": -1,
            "high": 1
        },
        "EBITDA_InterestExpense_vola": {
            "low": 0.15855967078189304,
            "high": 2
        },
        "CashConversionCycle_tren": {
            "low": -1,
            "high": -1
        },
        "CashConversionCycle_vola": {
            "low": 2,
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
        "current_ratio": 1.6357069143446852,
        "quick_ratio": 1.214972455282341,
        "debt_to_assets": 0.6091366378044514,
        "roa": 0.0017963916003440267,
        "net_margin": 0.03118375490393562,
        "asset_turnover": 0.3748279616646483,
        "inventory_turnover": 2.7994634643025664,
        "cash_ratio": 0.3768882977500412,
        "operating_margin": 0.06251518833535845,
        "debt_to_equity": 0.8725473701940115,
        "current_ratio_tren": 0.051976804648473096,
        "quick_ratio_tren": 0.17196249264144026,
        "debt_to_assets_tren": 0.011123307464965232,
        "roa_tren": 0.005764252124634892,
        "net_margin_tren": 0.0619602635423701,
        "asset_turnover_tren": 0.09733804391795763,
        "inventory_turnover_tren": -0.06554717118693495,
        "cash_ratio_tren": 0.016731694567267447,
        "operating_margin_tren": -0.039227271473500636,
        "debt_to_equity_tren": -0.23149249202711794,
        "EBIT_InterestExpense_tren": 0.1442625839406544,
        "CFO_Liabilities_tren": -0.004213377884669933,
        "TL_TA_tren": 0.011123307464965232,
        "Debt_Assets_tren": -0.03414445995669337,
        "WC_TA_tren": 0.00949303170062323,
        "EBITDA_InterestExpense_tren": 1,
        "CFO_DebtService_tren": -1,
        "ROA_tren": 0.005764252124634892,
        "OperatingMargin_tren": -0.039227271473500636,
        "DaysAR_tren": -1,
        "DaysINV_tren": 1,
        "DaysAP_tren": -1,
        "CashConversionCycle_tren": -1,
        "DividendOmission_tren": 0,
        "DebtIssuanceSpike_tren": 0,
        "DebtRepaymentSpike_tren": 0,
        "Accruals_tren": 0.00800741557289121,
        "current_ratio_vola": 0.8471571163986026,
        "quick_ratio_vola": 0.5203864080607437,
        "debt_to_assets_vola": 0.19898486720208647,
        "roa_vola": 0.055256557405371456,
        "net_margin_vola": 0.1018966524894555,
        "asset_turnover_vola": 0.25469994129605644,
        "inventory_turnover_vola": 0.7595586219186325,
        "cash_ratio_vola": 0.3569962601928306,
        "operating_margin_vola": 0.11241936514340878,
        "debt_to_equity_vola": 1.2183155087611022,
        "EBIT_InterestExpense_vola": 2,
        "CFO_Liabilities_vola": 0.10811176885847984,
        "TL_TA_vola": 0.19898486720208647,
        "Debt_Assets_vola": 0.10240673860787357,
        "WC_TA_vola": 0.19692014575476816,
        "EBITDA_InterestExpense_vola": 2,
        "CFO_DebtService_vola": 2,
        "ROA_vola": 0.055256557405371456,
        "OperatingMargin_vola": 0.11241936514340878,
        "DaysAR_vola": 2,
        "DaysINV_vola": 2,
        "DaysAP_vola": 2,
        "CashConversionCycle_vola": 2,
        "DividendOmission_vola": 0,
        "DebtIssuanceSpike_vola": 0,
        "DebtRepaymentSpike_vola": 0,
        "Accruals_vola": 0.04252518904869426,
        "ln_assets": 20.398355305484504,
        "EBIT_InterestExpense": 0.9824236817761333,
        "CFO_Liabilities": 0.011645383218499547,
        "TL_TA": 0.6091366378044514,
        "Debt_Assets": 0.223074863924333,
        "WC_TA": 0.1172090920631877,
        "EBITDA_InterestExpense": 2.727472248383477,
        "CFO_DebtService": 2,
        "ROA": 0.0017963916003440267,
        "OperatingMargin": 0.06251518833535845,
        "DaysAR": 82.45241809672386,
        "DaysINV": 127.10871629485816,
        "DaysAP": 71.06194690265487,
        "CashConversionCycle": 131.52957832322693,
        "DividendOmission": 0,
        "DebtIssuanceSpike": 0,
        "DebtRepaymentSpike": 0,
        "Accruals": -0.03329120877067786,
        "leverage_profitability": 0,
        "liquidity_cashflow": -5.32208508822692e-6,
        "size_profitability": 0.6512516639103225,
        "leverage_margin": 0.03092893864566268,
        "liquidity_accruals": -0.057303162367415686,
        "AltmanZPrime": 0.15447499803649
    },
    "indicatorNames": {
        "company_id": "company_id_missing",
        "fiscal_year": "fiscal_year_missing",
        "label": "label_missing",
        "current_ratio": "current_ratio_missing",
        "quick_ratio": "quick_ratio_missing",
        "debt_to_assets": "debt_to_assets_missing",
        "roa": "roa_missing",
        "net_margin": "net_margin_missing",
        "asset_turnover": "asset_turnover_missing",
        "inventory_turnover": "inventory_turnover_missing",
        "cash_ratio": "cash_ratio_missing",
        "operating_margin": "operating_margin_missing",
        "debt_to_equity": "debt_to_equity_missing",
        "current_ratio_tren": "current_ratio_tren_missing",
        "quick_ratio_tren": "quick_ratio_tren_missing",
        "debt_to_assets_tren": "debt_to_assets_tren_missing",
        "roa_tren": "roa_tren_missing",
        "net_margin_tren": "net_margin_tren_missing",
        "asset_turnover_tren": "asset_turnover_tren_missing",
        "inventory_turnover_tren": "inventory_turnover_tren_missing",
        "cash_ratio_tren": "cash_ratio_tren_missing",
        "operating_margin_tren": "operating_margin_tren_missing",
        "debt_to_equity_tren": "debt_to_equity_tren_missing",
        "EBIT_InterestExpense_tren": "EBIT_InterestExpense_tren_missing",
        "CFO_Liabilities_tren": "CFO_Liabilities_tren_missing",
        "TL_TA_tren": "TL_TA_tren_missing",
        "Debt_Assets_tren": "Debt_Assets_tren_missing",
        "WC_TA_tren": "WC_TA_tren_missing",
        "EBITDA_InterestExpense_tren": "EBITDA_InterestExpense_tren_missing",
        "CFO_DebtService_tren": "CFO_DebtService_tren_missing",
        "ROA_tren": "ROA_tren_missing",
        "OperatingMargin_tren": "OperatingMargin_tren_missing",
        "DaysAR_tren": "DaysAR_tren_missing",
        "DaysINV_tren": "DaysINV_tren_missing",
        "DaysAP_tren": "DaysAP_tren_missing",
        "CashConversionCycle_tren": "CashConversionCycle_tren_missing",
        "DividendOmission_tren": "DividendOmission_tren_missing",
        "DebtIssuanceSpike_tren": "DebtIssuanceSpike_tren_missing",
        "DebtRepaymentSpike_tren": "DebtRepaymentSpike_tren_missing",
        "Accruals_tren": "Accruals_tren_missing",
        "current_ratio_vola": "current_ratio_vola_missing",
        "quick_ratio_vola": "quick_ratio_vola_missing",
        "debt_to_assets_vola": "debt_to_assets_vola_missing",
        "roa_vola": "roa_vola_missing",
        "net_margin_vola": "net_margin_vola_missing",
        "asset_turnover_vola": "asset_turnover_vola_missing",
        "inventory_turnover_vola": "inventory_turnover_vola_missing",
        "cash_ratio_vola": "cash_ratio_vola_missing",
        "operating_margin_vola": "operating_margin_vola_missing",
        "debt_to_equity_vola": "debt_to_equity_vola_missing",
        "EBIT_InterestExpense_vola": "EBIT_InterestExpense_vola_missing",
        "CFO_Liabilities_vola": "CFO_Liabilities_vola_missing",
        "TL_TA_vola": "TL_TA_vola_missing",
        "Debt_Assets_vola": "Debt_Assets_vola_missing",
        "WC_TA_vola": "WC_TA_vola_missing",
        "EBITDA_InterestExpense_vola": "EBITDA_InterestExpense_vola_missing",
        "CFO_DebtService_vola": "CFO_DebtService_vola_missing",
        "ROA_vola": "ROA_vola_missing",
        "OperatingMargin_vola": "OperatingMargin_vola_missing",
        "DaysAR_vola": "DaysAR_vola_missing",
        "DaysINV_vola": "DaysINV_vola_missing",
        "DaysAP_vola": "DaysAP_vola_missing",
        "CashConversionCycle_vola": "CashConversionCycle_vola_missing",
        "DividendOmission_vola": "DividendOmission_vola_missing",
        "DebtIssuanceSpike_vola": "DebtIssuanceSpike_vola_missing",
        "DebtRepaymentSpike_vola": "DebtRepaymentSpike_vola_missing",
        "Accruals_vola": "Accruals_vola_missing",
        "ln_assets": "ln_assets_missing",
        "EBIT_InterestExpense": "EBIT_InterestExpense_missing",
        "CFO_Liabilities": "CFO_Liabilities_missing",
        "TL_TA": "TL_TA_missing",
        "Debt_Assets": "Debt_Assets_missing",
        "WC_TA": "WC_TA_missing",
        "EBITDA_InterestExpense": "EBITDA_InterestExpense_missing",
        "CFO_DebtService": "CFO_DebtService_missing",
        "ROA": "ROA_missing",
        "OperatingMargin": "OperatingMargin_missing",
        "DaysAR": "DaysAR_missing",
        "DaysINV": "DaysINV_missing",
        "DaysAP": "DaysAP_missing",
        "CashConversionCycle": "CashConversionCycle_missing",
        "DividendOmission": "DividendOmission_missing",
        "DebtIssuanceSpike": "DebtIssuanceSpike_missing",
        "DebtRepaymentSpike": "DebtRepaymentSpike_missing",
        "Accruals": "Accruals_missing",
        "leverage_profitability": "leverage_profitability_missing",
        "liquidity_cashflow": "liquidity_cashflow_missing",
        "size_profitability": "size_profitability_missing",
        "leverage_margin": "leverage_margin_missing",
        "liquidity_accruals": "liquidity_accruals_missing",
        "AltmanZPrime": "AltmanZPrime_missing"
    }
}





# Robust scaler medians & IQRs

{
    "medians": {
        "company_id": 5055,
        "fiscal_year": 2019,
        "label": 1,
        "current_ratio": 1.6357069143446852,
        "quick_ratio": 1.214972455282341,
        "debt_to_assets": 0.6091366378044514,
        "roa": 0.0017963916003440267,
        "net_margin": 0.03118375490393562,
        "asset_turnover": 0.3748279616646483,
        "inventory_turnover": 2.7994634643025664,
        "cash_ratio": 0.3768882977500412,
        "operating_margin": 0.06251518833535845,
        "debt_to_equity": 0.8725473701940115,
        "current_ratio_tren": 0.051976804648473096,
        "quick_ratio_tren": 0.17196249264144026,
        "debt_to_assets_tren": 0.011123307464965232,
        "roa_tren": 0.005764252124634892,
        "net_margin_tren": 0.0619602635423701,
        "asset_turnover_tren": 0.09733804391795763,
        "inventory_turnover_tren": -0.06554717118693495,
        "cash_ratio_tren": 0.016731694567267447,
        "operating_margin_tren": -0.039227271473500636,
        "debt_to_equity_tren": -0.23149249202711794,
        "EBIT_InterestExpense_tren": 0.1442625839406544,
        "CFO_Liabilities_tren": -0.004213377884669933,
        "TL_TA_tren": 0.011123307464965232,
        "Debt_Assets_tren": -0.03414445995669337,
        "WC_TA_tren": 0.00949303170062323,
        "EBITDA_InterestExpense_tren": 1,
        "CFO_DebtService_tren": -1,
        "ROA_tren": 0.005764252124634892,
        "OperatingMargin_tren": -0.039227271473500636,
        "DaysAR_tren": -1,
        "DaysINV_tren": 1,
        "DaysAP_tren": -1,
        "CashConversionCycle_tren": -1,
        "DividendOmission_tren": 0,
        "DebtIssuanceSpike_tren": 0,
        "DebtRepaymentSpike_tren": 0,
        "Accruals_tren": 0.00800741557289121,
        "current_ratio_vola": 0.8471571163986026,
        "quick_ratio_vola": 0.5203864080607437,
        "debt_to_assets_vola": 0.19898486720208647,
        "roa_vola": 0.055256557405371456,
        "net_margin_vola": 0.1018966524894555,
        "asset_turnover_vola": 0.25469994129605644,
        "inventory_turnover_vola": 0.7595586219186325,
        "cash_ratio_vola": 0.3569962601928306,
        "operating_margin_vola": 0.11241936514340878,
        "debt_to_equity_vola": 1.2183155087611022,
        "EBIT_InterestExpense_vola": 2,
        "CFO_Liabilities_vola": 0.10811176885847984,
        "TL_TA_vola": 0.19898486720208647,
        "Debt_Assets_vola": 0.10240673860787357,
        "WC_TA_vola": 0.19692014575476816,
        "EBITDA_InterestExpense_vola": 2,
        "CFO_DebtService_vola": 2,
        "ROA_vola": 0.055256557405371456,
        "OperatingMargin_vola": 0.11241936514340878,
        "DaysAR_vola": 2,
        "DaysINV_vola": 2,
        "DaysAP_vola": 2,
        "CashConversionCycle_vola": 2,
        "DividendOmission_vola": 0,
        "DebtIssuanceSpike_vola": 0,
        "DebtRepaymentSpike_vola": 0,
        "Accruals_vola": 0.04252518904869426,
        "ln_assets": 20.398355305484504,
        "EBIT_InterestExpense": 0.9824236817761333,
        "CFO_Liabilities": 0.011645383218499547,
        "TL_TA": 0.6091366378044514,
        "Debt_Assets": 0.223074863924333,
        "WC_TA": 0.1172090920631877,
        "EBITDA_InterestExpense": 2.727472248383477,
        "CFO_DebtService": 2,
        "ROA": 0.0017963916003440267,
        "OperatingMargin": 0.06251518833535845,
        "DaysAR": 82.45241809672386,
        "DaysINV": 127.10871629485816,
        "DaysAP": 71.06194690265487,
        "CashConversionCycle": 131.52957832322693,
        "DividendOmission": 0,
        "DebtIssuanceSpike": 0,
        "DebtRepaymentSpike": 0,
        "Accruals": -0.03329120877067786,
        "leverage_profitability": 0,
        "liquidity_cashflow": -5.32208508822692e-6,
        "size_profitability": 0.6512516639103225,
        "leverage_margin": 0.03092893864566268,
        "liquidity_accruals": -0.057303162367415686,
        "AltmanZPrime": 0.15447499803649,
        "company_id_missing": 0,
        "fiscal_year_missing": 0,
        "label_missing": 0,
        "current_ratio_missing": 0,
        "quick_ratio_missing": 1,
        "debt_to_assets_missing": 0,
        "roa_missing": 0,
        "net_margin_missing": 1,
        "asset_turnover_missing": 1,
        "inventory_turnover_missing": 1,
        "cash_ratio_missing": 0,
        "operating_margin_missing": 1,
        "debt_to_equity_missing": 0,
        "current_ratio_tren_missing": 1,
        "quick_ratio_tren_missing": 1,
        "debt_to_assets_tren_missing": 1,
        "roa_tren_missing": 1,
        "net_margin_tren_missing": 1,
        "asset_turnover_tren_missing": 1,
        "inventory_turnover_tren_missing": 1,
        "cash_ratio_tren_missing": 1,
        "operating_margin_tren_missing": 1,
        "debt_to_equity_tren_missing": 1,
        "EBIT_InterestExpense_tren_missing": 1,
        "CFO_Liabilities_tren_missing": 1,
        "TL_TA_tren_missing": 1,
        "Debt_Assets_tren_missing": 1,
        "WC_TA_tren_missing": 1,
        "EBITDA_InterestExpense_tren_missing": 1,
        "CFO_DebtService_tren_missing": 1,
        "ROA_tren_missing": 1,
        "OperatingMargin_tren_missing": 1,
        "DaysAR_tren_missing": 1,
        "DaysINV_tren_missing": 1,
        "DaysAP_tren_missing": 1,
        "CashConversionCycle_tren_missing": 1,
        "DividendOmission_tren_missing": 1,
        "DebtIssuanceSpike_tren_missing": 1,
        "DebtRepaymentSpike_tren_missing": 1,
        "Accruals_tren_missing": 1,
        "current_ratio_vola_missing": 1,
        "quick_ratio_vola_missing": 1,
        "debt_to_assets_vola_missing": 1,
        "roa_vola_missing": 1,
        "net_margin_vola_missing": 1,
        "asset_turnover_vola_missing": 1,
        "inventory_turnover_vola_missing": 1,
        "cash_ratio_vola_missing": 1,
        "operating_margin_vola_missing": 1,
        "debt_to_equity_vola_missing": 1,
        "EBIT_InterestExpense_vola_missing": 1,
        "CFO_Liabilities_vola_missing": 1,
        "TL_TA_vola_missing": 1,
        "Debt_Assets_vola_missing": 1,
        "WC_TA_vola_missing": 1,
        "EBITDA_InterestExpense_vola_missing": 1,
        "CFO_DebtService_vola_missing": 1,
        "ROA_vola_missing": 1,
        "OperatingMargin_vola_missing": 1,
        "DaysAR_vola_missing": 1,
        "DaysINV_vola_missing": 1,
        "DaysAP_vola_missing": 1,
        "CashConversionCycle_vola_missing": 1,
        "DividendOmission_vola_missing": 1,
        "DebtIssuanceSpike_vola_missing": 1,
        "DebtRepaymentSpike_vola_missing": 1,
        "Accruals_vola_missing": 1,
        "ln_assets_missing": 0,
        "EBIT_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 0,
        "TL_TA_missing": 0,
        "Debt_Assets_missing": 1,
        "WC_TA_missing": 0,
        "EBITDA_InterestExpense_missing": 1,
        "CFO_DebtService_missing": 0,
        "ROA_missing": 0,
        "OperatingMargin_missing": 1,
        "DaysAR_missing": 1,
        "DaysINV_missing": 1,
        "DaysAP_missing": 1,
        "CashConversionCycle_missing": 1,
        "DividendOmission_missing": 0,
        "DebtIssuanceSpike_missing": 0,
        "DebtRepaymentSpike_missing": 0,
        "Accruals_missing": 0,
        "leverage_profitability_missing": 0,
        "liquidity_cashflow_missing": 0,
        "size_profitability_missing": 1,
        "leverage_margin_missing": 1,
        "liquidity_accruals_missing": 0,
        "AltmanZPrime_missing": 0
    },
    "iqr": {
        "company_id": 5100,
        "fiscal_year": 5,
        "label": 1,
        "current_ratio": 1.3676322042908153,
        "quick_ratio": 1,
        "debt_to_assets": 0.4158016900651576,
        "roa": 0.08432842022844547,
        "net_margin": 1,
        "asset_turnover": 1,
        "inventory_turnover": 1,
        "cash_ratio": 0.5308591631419137,
        "operating_margin": 1,
        "debt_to_equity": 1.6651800547568492,
        "current_ratio_tren": 1,
        "quick_ratio_tren": 1,
        "debt_to_assets_tren": 1,
        "roa_tren": 1,
        "net_margin_tren": 1,
        "asset_turnover_tren": 1,
        "inventory_turnover_tren": 1,
        "cash_ratio_tren": 1,
        "operating_margin_tren": 1,
        "debt_to_equity_tren": 1,
        "EBIT_InterestExpense_tren": 1,
        "CFO_Liabilities_tren": 1,
        "TL_TA_tren": 1,
        "Debt_Assets_tren": 1,
        "WC_TA_tren": 1,
        "EBITDA_InterestExpense_tren": 1,
        "CFO_DebtService_tren": 1,
        "ROA_tren": 1,
        "OperatingMargin_tren": 1,
        "DaysAR_tren": 1,
        "DaysINV_tren": 1,
        "DaysAP_tren": 1,
        "CashConversionCycle_tren": 1,
        "DividendOmission_tren": 1,
        "DebtIssuanceSpike_tren": 1,
        "DebtRepaymentSpike_tren": 1,
        "Accruals_tren": 1,
        "current_ratio_vola": 1,
        "quick_ratio_vola": 1,
        "debt_to_assets_vola": 1,
        "roa_vola": 1,
        "net_margin_vola": 1,
        "asset_turnover_vola": 1,
        "inventory_turnover_vola": 1,
        "cash_ratio_vola": 1,
        "operating_margin_vola": 1,
        "debt_to_equity_vola": 1,
        "EBIT_InterestExpense_vola": 1,
        "CFO_Liabilities_vola": 1,
        "TL_TA_vola": 1,
        "Debt_Assets_vola": 1,
        "WC_TA_vola": 1,
        "EBITDA_InterestExpense_vola": 1,
        "CFO_DebtService_vola": 1,
        "ROA_vola": 1,
        "OperatingMargin_vola": 1,
        "DaysAR_vola": 1,
        "DaysINV_vola": 1,
        "DaysAP_vola": 1,
        "CashConversionCycle_vola": 1,
        "DividendOmission_vola": 1,
        "DebtIssuanceSpike_vola": 1,
        "DebtRepaymentSpike_vola": 1,
        "Accruals_vola": 1,
        "ln_assets": 4.647197946471426,
        "EBIT_InterestExpense": 1,
        "CFO_Liabilities": 0.08289438012041506,
        "TL_TA": 0.4158016900651576,
        "Debt_Assets": 1,
        "WC_TA": 0.2656443550983443,
        "EBITDA_InterestExpense": 1,
        "CFO_DebtService": 0.5455284361859569,
        "ROA": 0.08432842022844547,
        "OperatingMargin": 1,
        "DaysAR": 1,
        "DaysINV": 1,
        "DaysAP": 1,
        "CashConversionCycle": 1,
        "DividendOmission": 1,
        "DebtIssuanceSpike": 1,
        "DebtRepaymentSpike": 1,
        "Accruals": 0.0600636246797173,
        "leverage_profitability": 0.020137625876271298,
        "liquidity_cashflow": 0.000280128990293437,
        "size_profitability": 1,
        "leverage_margin": 1,
        "liquidity_accruals": 0.05612162656791119,
        "AltmanZPrime": 1.3500636085814,
        "company_id_missing": 1,
        "fiscal_year_missing": 1,
        "label_missing": 1,
        "current_ratio_missing": 1,
        "quick_ratio_missing": 1,
        "debt_to_assets_missing": 1,
        "roa_missing": 1,
        "net_margin_missing": 1,
        "asset_turnover_missing": 1,
        "inventory_turnover_missing": 1,
        "cash_ratio_missing": 1,
        "operating_margin_missing": 1,
        "debt_to_equity_missing": 1,
        "current_ratio_tren_missing": 1,
        "quick_ratio_tren_missing": 1,
        "debt_to_assets_tren_missing": 1,
        "roa_tren_missing": 1,
        "net_margin_tren_missing": 1,
        "asset_turnover_tren_missing": 1,
        "inventory_turnover_tren_missing": 1,
        "cash_ratio_tren_missing": 1,
        "operating_margin_tren_missing": 1,
        "debt_to_equity_tren_missing": 1,
        "EBIT_InterestExpense_tren_missing": 1,
        "CFO_Liabilities_tren_missing": 1,
        "TL_TA_tren_missing": 1,
        "Debt_Assets_tren_missing": 1,
        "WC_TA_tren_missing": 1,
        "EBITDA_InterestExpense_tren_missing": 1,
        "CFO_DebtService_tren_missing": 1,
        "ROA_tren_missing": 1,
        "OperatingMargin_tren_missing": 1,
        "DaysAR_tren_missing": 1,
        "DaysINV_tren_missing": 1,
        "DaysAP_tren_missing": 1,
        "CashConversionCycle_tren_missing": 1,
        "DividendOmission_tren_missing": 1,
        "DebtIssuanceSpike_tren_missing": 1,
        "DebtRepaymentSpike_tren_missing": 1,
        "Accruals_tren_missing": 1,
        "current_ratio_vola_missing": 1,
        "quick_ratio_vola_missing": 1,
        "debt_to_assets_vola_missing": 1,
        "roa_vola_missing": 1,
        "net_margin_vola_missing": 1,
        "asset_turnover_vola_missing": 1,
        "inventory_turnover_vola_missing": 1,
        "cash_ratio_vola_missing": 1,
        "operating_margin_vola_missing": 1,
        "debt_to_equity_vola_missing": 1,
        "EBIT_InterestExpense_vola_missing": 1,
        "CFO_Liabilities_vola_missing": 1,
        "TL_TA_vola_missing": 1,
        "Debt_Assets_vola_missing": 1,
        "WC_TA_vola_missing": 1,
        "EBITDA_InterestExpense_vola_missing": 1,
        "CFO_DebtService_vola_missing": 1,
        "ROA_vola_missing": 1,
        "OperatingMargin_vola_missing": 1,
        "DaysAR_vola_missing": 1,
        "DaysINV_vola_missing": 1,
        "DaysAP_vola_missing": 1,
        "CashConversionCycle_vola_missing": 1,
        "DividendOmission_vola_missing": 1,
        "DebtIssuanceSpike_vola_missing": 1,
        "DebtRepaymentSpike_vola_missing": 1,
        "Accruals_vola_missing": 1,
        "ln_assets_missing": 1,
        "EBIT_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 1,
        "TL_TA_missing": 1,
        "Debt_Assets_missing": 1,
        "WC_TA_missing": 1,
        "EBITDA_InterestExpense_missing": 1,
        "CFO_DebtService_missing": 1,
        "ROA_missing": 1,
        "OperatingMargin_missing": 1,
        "DaysAR_missing": 1,
        "DaysINV_missing": 1,
        "DaysAP_missing": 1,
        "CashConversionCycle_missing": 1,
        "DividendOmission_missing": 1,
        "DebtIssuanceSpike_missing": 1,
        "DebtRepaymentSpike_missing": 1,
        "Accruals_missing": 1,
        "leverage_profitability_missing": 1,
        "liquidity_cashflow_missing": 1,
        "size_profitability_missing": 1,
        "leverage_margin_missing": 1,
        "liquidity_accruals_missing": 1,
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