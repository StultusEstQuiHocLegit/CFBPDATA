# General Summary

Logistic regression bankruptcy classifier trained on grouped annual filings (validation year 2023, test year 2024) with inverse-frequency class weighting and isotonic calibration. Latest model snapshot: 2025-10-18T17:10:42+02:00.

    Optimisation target: precision-recall AUC (PR AUC = 0.5861, ROC AUC = 0.4812, Brier score = 0.2432).
    Calibration thresholds (isotonic): probability grid [0, 0.4999, 0.4999, 0.5, 0.5, 0.5, 0.5, 0.5, 1] -> calibrated scores [0.4884, 0.4884, 0.5, 0.5385, 0.5533, 0.585, 0.6139, 0.6667, 0.6667].
    Primary decision point (threshold 0.5851) yields TP=119, FP=75, TN=567, FN=820 (precision 0.6134, recall 0.1267, F1 0.2101). Strict recall 0.8 uses threshold 0.5459 with TP=753, FP=541, TN=101, FN=186 (precision 0.5819, recall 0.8019, F1 0.6744).
    Calibration reliability (test deciles): [0.49-0.54]=0.52->0.59, [0.54-0.55]=0.54->0.74, [0.55-0.55]=0.55->0.67, [0.55-0.55]=0.55->0.55, [0.55-0.55]=0.55->0.44.
    L2 regularisation λ=0.01, 400 gradient-descent epochs, learning rate 0.05. Bias = 0.










# Training metadata & feature order

{
    "timestamp": "2025-10-18T17:10:42+02:00",
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
        "debt_to_assets",
        "roa",
        "ln_assets",
        "EBIT_InterestExpense",
        "CFO_Liabilities",
        "AltmanZPrime",
        "company_id_missing",
        "fiscal_year_missing",
        "label_missing",
        "current_ratio_missing",
        "debt_to_assets_missing",
        "roa_missing",
        "ln_assets_missing",
        "EBIT_InterestExpense_missing",
        "CFO_Liabilities_missing",
        "AltmanZPrime_missing"
    ]
}










# Evaluation metrics (validation/test)

{
    "pr_auc": 0.5861410064949505,
    "roc_auc": 0.4812088819881957,
    "brier": 0.24318189863806253,
    "thresholds": {
        "primary": 0.5850802350363027,
        "f1_max": 0.4883720930232558,
        "recall_target": 0.545888582110079,
        "best": 0.5850802350363027,
        "recall80": 0.545888582110079
    },
    "operating_points": {
        "validation": {
            "primary": {
                "threshold": 0.5850802350363027,
                "precision": 0.6149068322981367,
                "recall": 0.10576923076923077,
                "f1": 0.1804922515952598,
                "support": {
                    "tp": 99,
                    "fp": 62,
                    "tn": 671,
                    "fn": 837
                }
            },
            "f1_max": {
                "threshold": 0.4883720930232558,
                "precision": 0.560814859197124,
                "recall": 1,
                "f1": 0.7186180422264875,
                "support": {
                    "tp": 936,
                    "fp": 733,
                    "tn": 0,
                    "fn": 0
                }
            },
            "recall_target": {
                "threshold": 0.545888582110079,
                "precision": 0.5491202346041055,
                "recall": 0.8002136752136753,
                "f1": 0.6513043478260869,
                "support": {
                    "tp": 749,
                    "fp": 615,
                    "tn": 118,
                    "fn": 187
                },
                "target_recall": 0.8
            }
        },
        "test": {
            "primary": {
                "threshold": 0.5850802350363027,
                "precision": 0.6134020618556701,
                "recall": 0.12673056443024494,
                "f1": 0.21006178287731686,
                "support": {
                    "tp": 119,
                    "fp": 75,
                    "tn": 567,
                    "fn": 820
                }
            },
            "f1_max": {
                "threshold": 0.4883720930232558,
                "precision": 0.5939278937381404,
                "recall": 1,
                "f1": 0.7452380952380953,
                "support": {
                    "tp": 939,
                    "fp": 642,
                    "tn": 0,
                    "fn": 0
                }
            },
            "recall_target": {
                "threshold": 0.545888582110079,
                "precision": 0.5819165378670789,
                "recall": 0.8019169329073482,
                "f1": 0.6744290192566055,
                "support": {
                    "tp": 753,
                    "fp": 541,
                    "tn": 101,
                    "fn": 186
                },
                "target_recall": 0.8
            }
        }
    },
    "reliability": {
        "validation": [
            {
                "bin": 1,
                "lower": 0.4883720930232558,
                "upper": 0.5431778602095084,
                "count": 167,
                "avg_pred": 0.521311638665468,
                "emp_rate": 0.5808383233532934
            },
            {
                "bin": 2,
                "lower": 0.5431994307911004,
                "upper": 0.5463754886606895,
                "count": 167,
                "avg_pred": 0.5449799330852503,
                "emp_rate": 0.6586826347305389
            },
            {
                "bin": 3,
                "lower": 0.54638702854833,
                "upper": 0.5496781495129824,
                "count": 167,
                "avg_pred": 0.5481401544244232,
                "emp_rate": 0.5089820359281437
            },
            {
                "bin": 4,
                "lower": 0.5496843469643058,
                "upper": 0.551147034045259,
                "count": 167,
                "avg_pred": 0.550570427378203,
                "emp_rate": 0.5748502994011976
            },
            {
                "bin": 5,
                "lower": 0.5511485178228243,
                "upper": 0.5517376740883253,
                "count": 167,
                "avg_pred": 0.5514677223253526,
                "emp_rate": 0.5269461077844312
            },
            {
                "bin": 6,
                "lower": 0.5517443049334216,
                "upper": 0.5523252608774407,
                "count": 167,
                "avg_pred": 0.5520193194844374,
                "emp_rate": 0.5089820359281437
            },
            {
                "bin": 7,
                "lower": 0.5523359425631887,
                "upper": 0.5529464623318263,
                "count": 167,
                "avg_pred": 0.5526473022244758,
                "emp_rate": 0.562874251497006
            },
            {
                "bin": 8,
                "lower": 0.5529471008870632,
                "upper": 0.5544782340232178,
                "count": 167,
                "avg_pred": 0.5532361282722078,
                "emp_rate": 0.49101796407185627
            },
            {
                "bin": 9,
                "lower": 0.5544917314371202,
                "upper": 0.5831623236524286,
                "count": 167,
                "avg_pred": 0.5638214506080518,
                "emp_rate": 0.592814371257485
            },
            {
                "bin": 10,
                "lower": 0.5831623236524286,
                "upper": 0.6666666666666666,
                "count": 166,
                "avg_pred": 0.5991868876613912,
                "emp_rate": 0.6024096385542169
            }
        ],
        "test": [
            {
                "bin": 1,
                "lower": 0.4883720930232558,
                "upper": 0.5424685590684861,
                "count": 159,
                "avg_pred": 0.5183633110590838,
                "emp_rate": 0.5911949685534591
            },
            {
                "bin": 2,
                "lower": 0.5425399438145843,
                "upper": 0.5463067235665182,
                "count": 159,
                "avg_pred": 0.544684927251839,
                "emp_rate": 0.7421383647798742
            },
            {
                "bin": 3,
                "lower": 0.5463198010580709,
                "upper": 0.5492093156811751,
                "count": 159,
                "avg_pred": 0.547745687576746,
                "emp_rate": 0.6666666666666666
            },
            {
                "bin": 4,
                "lower": 0.549222394777558,
                "upper": 0.5510889345851028,
                "count": 159,
                "avg_pred": 0.5503374149094201,
                "emp_rate": 0.5471698113207547
            },
            {
                "bin": 5,
                "lower": 0.5510918448973505,
                "upper": 0.5516553319737667,
                "count": 159,
                "avg_pred": 0.5514197938838161,
                "emp_rate": 0.44025157232704404
            },
            {
                "bin": 6,
                "lower": 0.5516617118589822,
                "upper": 0.5522695829572914,
                "count": 159,
                "avg_pred": 0.5519669374220981,
                "emp_rate": 0.559748427672956
            },
            {
                "bin": 7,
                "lower": 0.552276807025038,
                "upper": 0.5528989188007127,
                "count": 159,
                "avg_pred": 0.5526042044065028,
                "emp_rate": 0.5786163522012578
            },
            {
                "bin": 8,
                "lower": 0.5529001405106405,
                "upper": 0.5593811023000465,
                "count": 159,
                "avg_pred": 0.5542301420398777,
                "emp_rate": 0.6415094339622641
            },
            {
                "bin": 9,
                "lower": 0.5593811023000465,
                "upper": 0.5954687209065296,
                "count": 159,
                "avg_pred": 0.5749063373911006,
                "emp_rate": 0.5408805031446541
            },
            {
                "bin": 10,
                "lower": 0.595603563348482,
                "upper": 0.6666666666666666,
                "count": 150,
                "avg_pred": 0.6028859442092344,
                "emp_rate": 0.6333333333333333
            }
        ]
    },
    "confusion_best": {
        "TP": 119,
        "FP": 75,
        "TN": 567,
        "FN": 820,
        "precision": 0.6134020618556701,
        "recall": 0.12673056443024494,
        "f1": 0.21006178287731686
    },
    "confusion_strict": {
        "TP": 753,
        "FP": 541,
        "TN": 101,
        "FN": 186,
        "precision": 0.5819165378670789,
        "recall": 0.8019169329073482,
        "f1": 0.6744290192566055
    }
}










# Calibration curve (isotonic)

{
    "thresholds": [
        0,
        0.49993746629765223,
        0.4999418672104517,
        0.4999543028072226,
        0.500003926078698,
        0.5000197994196258,
        0.5000439775299267,
        0.5000456063861044,
        1
    ],
    "values": [
        0.4883720930232558,
        0.4883720930232558,
        0.5,
        0.5384615384615384,
        0.5532786885245902,
        0.585,
        0.6139240506329114,
        0.6666666666666666,
        0.6666666666666666
    ]
}










# Model coefficients (bias, λ, iterations, learning rate, per-feature weights)

{
    "weights": [
        -5.005988441162104e-7,
        -1.4362868755979453e-5,
        4.294485114425752e-6,
        7.775521803595276e-6,
        1.2954628097509e-5,
        2.5487412018652383e-6,
        5.014938304680551e-6,
        0,
        0,
        0,
        -4.496043902402929e-6,
        1.8000672779143616e-6,
        -2.098918681844058e-6,
        -3.11177812848854e-7,
        -4.975819829058308e-6,
        -8.356941087899685e-7,
        0
    ],
    "bias": 4.508492207960027e-19,
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
        "ln_assets": 20.360486585288182,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "AltmanZPrime": 0.15447499803649
    },
    "indicatorNames": {
        "company_id": "company_id_missing",
        "fiscal_year": "fiscal_year_missing",
        "label": "label_missing",
        "current_ratio": "current_ratio_missing",
        "debt_to_assets": "debt_to_assets_missing",
        "roa": "roa_missing",
        "ln_assets": "ln_assets_missing",
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
        "debt_to_assets": 0.6142431242943835,
        "roa": 0.0021612117453119477,
        "ln_assets": 20.360486585288182,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "AltmanZPrime": 0.15447499803649,
        "company_id_missing": 0,
        "fiscal_year_missing": 0,
        "label_missing": 0,
        "current_ratio_missing": 0,
        "debt_to_assets_missing": 0,
        "roa_missing": 0,
        "ln_assets_missing": 0,
        "EBIT_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 0,
        "AltmanZPrime_missing": 0
    },
    "iqr": {
        "company_id": 5100,
        "fiscal_year": 5,
        "label": 1,
        "current_ratio": 1.3827459050619193,
        "debt_to_assets": 0.42126595758989516,
        "roa": 0.08231505469191362,
        "ln_assets": 4.702217012571836,
        "EBIT_InterestExpense": 1,
        "CFO_Liabilities": 0.07649589798301172,
        "AltmanZPrime": 1.3500636085814,
        "company_id_missing": 1,
        "fiscal_year_missing": 1,
        "label_missing": 1,
        "current_ratio_missing": 1,
        "debt_to_assets_missing": 1,
        "roa_missing": 1,
        "ln_assets_missing": 1,
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