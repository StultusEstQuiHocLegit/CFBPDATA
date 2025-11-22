# General Summary

Logistic regression bankruptcy classifier trained on grouped annual filings (validation year 2023, test year 2024) with inverse-frequency class weighting and isotonic calibration. Latest model snapshot: 2025-10-18T11:08:54+02:00.

    Optimisation target: precision-recall AUC (PR AUC = 0.6109, ROC AUC = 0.5103, Brier score = 0.2455).
    Calibration thresholds (isotonic): probability grid [0, 0.4814, 0.4815, 0.4886, 0.4938, 0.4989, 0.5004, 0.5005, 0.5799, 1] -> calibrated scores [0, 0, 0.2, 0.4167, 0.45, 0.5256, 0.5539, 0.5714, 0.66, 0.66].
    "Best" decision point yields TP=101, FP=51, TN=591, FN=838 (precision 0.6645, recall 0.1076, F1 0.1852), strict recall at 0.8 uses threshold 0.54.
    L2 regularisation λ=0.01, 400 gradient-descent epochs, learning rate 0.05. Bias = 0.










# Training metadata & feature order

{
    "timestamp": "2025-10-18T11:08:54+02:00",
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
            "min_probability_bins": 6,
            "l2_bin_retry_factor": 5,
            "max_bin_retries": 3,
            "seed": 42
        },
        "calibration": "isotonic",
        "thresholds": {
            "optimize_for": "pr_auc",
            "strict_recall_at": 0.8
        }
    },
    "feature_names": [
        "current_ratio",
        "quick_ratio",
        "debt_to_assets",
        "roa",
        "operating_margin",
        "ln_assets",
        "TL_TA",
        "WC_TA",
        "EBIT_InterestExpense",
        "CFO_Liabilities",
        "AltmanZPrime",
        "company_id_missing",
        "fiscal_year_missing",
        "label_missing",
        "current_ratio_missing",
        "quick_ratio_missing",
        "debt_to_assets_missing",
        "roa_missing",
        "operating_margin_missing",
        "ln_assets_missing",
        "TL_TA_missing",
        "WC_TA_missing",
        "EBIT_InterestExpense_missing",
        "CFO_Liabilities_missing",
        "AltmanZPrime_missing"
    ]
}










# Evaluation metrics (validation/test)

{
    "pr_auc": 0.61091301174891,
    "roc_auc": 0.5102564868173539,
    "brier": 0.2454958015902562,
    "thresholds": {
        "best": 0.5714673263568024,
        "recall80": 0.54
    },
    "confusion_best": {
        "TP": 101,
        "FP": 51,
        "TN": 591,
        "FN": 838,
        "precision": 0.6644736842105263,
        "recall": 0.10756123535676251,
        "f1": 0.18515123739688358
    },
    "confusion_strict": {
        "TP": 732,
        "FP": 518,
        "TN": 124,
        "FN": 207,
        "precision": 0.5856,
        "recall": 0.7795527156549521,
        "f1": 0.6687985381452719
    }
}










# Calibration curve (isotonic)

{
    "thresholds": [
        0,
        0.4813727185150481,
        0.48147126069324647,
        0.4885887824230506,
        0.4937606786722724,
        0.4989065515001288,
        0.5004217937745807,
        0.5004959995260995,
        0.5799354321110212,
        1
    ],
    "values": [
        0,
        0,
        0.2,
        0.4166666666666667,
        0.45,
        0.5256410256410257,
        0.5538809344385832,
        0.5714285714285714,
        0.66,
        0.66
    ]
}










# Model coefficients (bias, λ, iterations, learning rate, per-feature weights)

{
    "weights": [
        -3.1827759796657912e-6,
        -8.684028114002593e-7,
        -0.00011068058324528435,
        5.0404511698408825e-5,
        -9.59444954667091e-6,
        1.1376553635425613e-5,
        -0.00011068058324528435,
        7.912231306636975e-5,
        -0.00022632787624299154,
        1.3969443157834252e-5,
        0.000160844718637713,
        0,
        0,
        0,
        -6.519218811289758e-6,
        -7.3135851458328455e-6,
        2.6045491583913127e-6,
        -3.0483966006095353e-6,
        -3.793431347356514e-6,
        -4.5108619406113514e-7,
        2.6045491583913127e-6,
        -6.8790482732448304e-6,
        -7.001156600302426e-6,
        -1.2437591186293908e-6,
        -4.5108619406113514e-7
    ],
    "bias": -1.849364033229478e-7,
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
            "high": 33.194319988437634
        },
        "roa": {
            "low": -4.383023161411501,
            "high": 0.18963814235529558
        },
        "ln_assets": {
            "low": 10.028444791940512,
            "high": 26.71271335121065
        },
        "TL_TA": {
            "low": 0.03238451332770474,
            "high": 33.194319988437634
        },
        "EBIT_InterestExpense": {
            "low": -1419,
            "high": 331.15384615384613
        },
        "AltmanZPrime": {
            "low": -172.77620749387,
            "high": 5.2091128096904
        },
        "current_ratio": {
            "low": 0.008923825353307421,
            "high": 22.387419165196942
        },
        "WC_TA": {
            "low": -23.61758691206544,
            "high": 0.9421048837475564
        },
        "CFO_Liabilities": {
            "low": -2.916105445449298,
            "high": 0.826068191627104
        },
        "quick_ratio": {
            "low": 0.00801435584371217,
            "high": 9.937777164828214
        },
        "operating_margin": {
            "low": -38.52888527257933,
            "high": 0.7730889457232168
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
        "quick_ratio": 1.2181551976573939,
        "debt_to_assets": 0.6142431242943835,
        "roa": 0.0021612117453119477,
        "operating_margin": 0.06281943597788078,
        "ln_assets": 20.360486585288182,
        "TL_TA": 0.6142431242943835,
        "WC_TA": 0.11865402294420357,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "AltmanZPrime": 0.15945958838452
    },
    "indicatorNames": {
        "company_id": "company_id_missing",
        "fiscal_year": "fiscal_year_missing",
        "label": "label_missing",
        "current_ratio": "current_ratio_missing",
        "quick_ratio": "quick_ratio_missing",
        "debt_to_assets": "debt_to_assets_missing",
        "roa": "roa_missing",
        "operating_margin": "operating_margin_missing",
        "ln_assets": "ln_assets_missing",
        "TL_TA": "TL_TA_missing",
        "WC_TA": "WC_TA_missing",
        "EBIT_InterestExpense": "EBIT_InterestExpense_missing",
        "CFO_Liabilities": "CFO_Liabilities_missing",
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
        "quick_ratio": 1.2181551976573939,
        "debt_to_assets": 0.6142431242943835,
        "roa": 0.0021612117453119477,
        "operating_margin": 0.06281943597788078,
        "ln_assets": 20.360486585288182,
        "TL_TA": 0.6142431242943835,
        "WC_TA": 0.11865402294420357,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "AltmanZPrime": 0.15945958838452,
        "company_id_missing": 0,
        "fiscal_year_missing": 0,
        "label_missing": 0,
        "current_ratio_missing": 0,
        "quick_ratio_missing": 1,
        "debt_to_assets_missing": 0,
        "roa_missing": 0,
        "operating_margin_missing": 1,
        "ln_assets_missing": 0,
        "TL_TA_missing": 0,
        "WC_TA_missing": 0,
        "EBIT_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 0,
        "AltmanZPrime_missing": 0
    },
    "iqr": {
        "company_id": 5100,
        "fiscal_year": 5,
        "label": 1,
        "current_ratio": 1.3827459050619193,
        "quick_ratio": 1,
        "debt_to_assets": 0.42126595758989516,
        "roa": 0.08231505469191362,
        "operating_margin": 1,
        "ln_assets": 4.702217012571836,
        "TL_TA": 0.42126595758989516,
        "WC_TA": 0.2692590139361537,
        "EBIT_InterestExpense": 1,
        "CFO_Liabilities": 0.07649589798301172,
        "AltmanZPrime": 1.28435834733447,
        "company_id_missing": 1,
        "fiscal_year_missing": 1,
        "label_missing": 1,
        "current_ratio_missing": 1,
        "quick_ratio_missing": 1,
        "debt_to_assets_missing": 1,
        "roa_missing": 1,
        "operating_margin_missing": 1,
        "ln_assets_missing": 1,
        "TL_TA_missing": 1,
        "WC_TA_missing": 1,
        "EBIT_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 1,
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