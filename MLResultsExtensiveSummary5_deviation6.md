# General Summary

Logistic regression bankruptcy classifier trained on grouped annual filings (validation year 2023, test year 2024) with inverse-frequency class weighting and isotonic calibration. Latest model snapshot: 2025-11-07T13:49:21+01:00.

    Optimisation target: F1 (PR AUC = 0.6899, ROC AUC = 0.6166, Brier score = 0.26).
    Calibration thresholds (isotonic): probability grid [0, 0.484, 0.4849, 0.485, 0.485, 0.485, 0.485, 0.485, 0.485, 0.485, 0.485, 0.485, 0.485, 0.4851, 0.4851, 0.4851, 0.4999, 0.5, 0.5, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5055, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 0.5056, 1] -> calibrated scores [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.3824, 0.5, 0.5, 0.7395, 0.9091, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1].
    Primary decision point (threshold N/A) yields TP=937, FP=620, TN=22, FN=2 (precision 0.6018, recall 0.9979, F1 0.7508). Strict recall 0.8 has no available threshold with TP=736, FP=492, TN=150, FN=203 (precision 0.5993, recall 0.7838, F1 0.6793).
    Calibration reliability (test deciles): [0-0.34]=0.18->0.57, [0.34-0.37]=0.36->0.58, [0.37-0.38]=0.37->0.52, [0.38-0.38]=0.38->0.3, [0.38-0.38]=0.38->0.21.
    L2 regularisation λ=0.01, 400 gradient-descent epochs, learning rate 0.05. Bias = 0.










# Training metadata & feature order

{
    "timestamp": "2025-11-07T13:49:21+01:00",
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
            "metric": "roc_auc"
        },
        "thresholds": {
            "optimize_for": "f1",
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
        "cv_metric": 0.5662736884868061,
        "cv_per_fold": [
            0.5695353498390734,
            0.5673918557461711,
            0.5618938598751739
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
    "pr_auc": 0.6899096611653968,
    "roc_auc": 0.6165727774294254,
    "brier": 0.2599728758409682,
    "thresholds": {
        "primary": {
            "threshold": 0.0008034915344757764,
            "expected_cost": 718,
            "f_beta": 0.7227799227799228
        },
        "f1_max": {
            "threshold": 0.0008034915344757764,
            "expected_cost": 718,
            "f_beta": 0.7227799227799228
        },
        "recall_target": {
            "threshold": 0.37132246294985216,
            "target_recall": 0.8,
            "expected_cost": 761,
            "f_beta": 0.6631252766710933
        },
        "best": 0.0008034915344757764,
        "recall80": 0.37132246294985216
    },
    "operating_points": {
        "validation": {
            "primary": {
                "threshold": 0.0008034915344757764,
                "precision": 0.56590084643289,
                "recall": 1,
                "f1": 0.7227799227799228,
                "expected_cost": 718,
                "f_beta": 0.7227799227799228,
                "support": {
                    "tp": 936,
                    "fp": 718,
                    "tn": 15,
                    "fn": 0
                },
                "threshold_index": 1
            },
            "f1_max": {
                "threshold": 0.0008034915344757764,
                "precision": 0.56590084643289,
                "recall": 1,
                "f1": 0.7227799227799228,
                "expected_cost": 718,
                "f_beta": 0.7227799227799228,
                "support": {
                    "tp": 936,
                    "fp": 718,
                    "tn": 15,
                    "fn": 0
                },
                "threshold_index": 1
            },
            "recall_target": {
                "threshold": 0.37132246294985216,
                "precision": 0.5661375661375662,
                "recall": 0.8002136752136753,
                "f1": 0.6631252766710933,
                "expected_cost": 761,
                "f_beta": 0.6631252766710933,
                "support": {
                    "tp": 749,
                    "fp": 574,
                    "tn": 159,
                    "fn": 187
                },
                "threshold_index": 332,
                "target_recall": 0.8
            }
        },
        "test": {
            "primary": {
                "threshold": 0.0008034915344757764,
                "precision": 0.6017983301220295,
                "recall": 0.9978700745473909,
                "f1": 0.7508012820512819,
                "support": {
                    "tp": 937,
                    "fp": 620,
                    "tn": 22,
                    "fn": 2
                },
                "expected_cost": 622,
                "f_beta": 0.7508012820512819,
                "threshold_index": 1
            },
            "f1_max": {
                "threshold": 0.0008034915344757764,
                "precision": 0.6017983301220295,
                "recall": 0.9978700745473909,
                "f1": 0.7508012820512819,
                "support": {
                    "tp": 937,
                    "fp": 620,
                    "tn": 22,
                    "fn": 2
                },
                "expected_cost": 622,
                "f_beta": 0.7508012820512819,
                "threshold_index": 1
            },
            "recall_target": {
                "threshold": 0.37132246294985216,
                "precision": 0.5993485342019544,
                "recall": 0.7838125665601704,
                "f1": 0.6792801107521921,
                "support": {
                    "tp": 736,
                    "fp": 492,
                    "tn": 150,
                    "fn": 203
                },
                "expected_cost": 695,
                "f_beta": 0.6792801107521921,
                "threshold_index": 332,
                "target_recall": 0.8
            }
        }
    },
    "reliability": {
        "validation": [
            {
                "bin": 1,
                "lower": 0,
                "upper": 0.3446971092116269,
                "count": 167,
                "avg_pred": 0.20390528163762828,
                "emp_rate": 0.5269461077844312
            },
            {
                "bin": 2,
                "lower": 0.3447731177665837,
                "upper": 0.37023714788887163,
                "count": 167,
                "avg_pred": 0.3616155464337315,
                "emp_rate": 0.5329341317365269
            },
            {
                "bin": 3,
                "lower": 0.3703392085077846,
                "upper": 0.3792783201540507,
                "count": 167,
                "avg_pred": 0.37559724225366764,
                "emp_rate": 0.47904191616766467
            },
            {
                "bin": 4,
                "lower": 0.3793454842257795,
                "upper": 0.3810459862045972,
                "count": 167,
                "avg_pred": 0.3803236689039801,
                "emp_rate": 0.1437125748502994
            },
            {
                "bin": 5,
                "lower": 0.38105290545287696,
                "upper": 0.38606302037523776,
                "count": 167,
                "avg_pred": 0.3816877515015091,
                "emp_rate": 0.20359281437125748
            },
            {
                "bin": 6,
                "lower": 0.3922603212568868,
                "upper": 0.502059974779091,
                "count": 167,
                "avg_pred": 0.4927876786443552,
                "emp_rate": 0.8023952095808383
            },
            {
                "bin": 7,
                "lower": 0.5020653080086489,
                "upper": 0.5036744811593707,
                "count": 167,
                "avg_pred": 0.5026817361317756,
                "emp_rate": 0.8862275449101796
            },
            {
                "bin": 8,
                "lower": 0.5036875991369608,
                "upper": 0.5155488212783739,
                "count": 167,
                "avg_pred": 0.5074237068621265,
                "emp_rate": 0.7604790419161677
            },
            {
                "bin": 9,
                "lower": 0.515810939485496,
                "upper": 0.6464031901870786,
                "count": 167,
                "avg_pred": 0.5587935876570818,
                "emp_rate": 0.6706586826347305
            },
            {
                "bin": 10,
                "lower": 0.6464669048989806,
                "upper": 1,
                "count": 166,
                "avg_pred": 0.7865937902733966,
                "emp_rate": 0.6024096385542169
            }
        ],
        "test": [
            {
                "bin": 1,
                "lower": 0,
                "upper": 0.33669407789637823,
                "count": 159,
                "avg_pred": 0.1797033693903085,
                "emp_rate": 0.5660377358490566
            },
            {
                "bin": 2,
                "lower": 0.33765351474397154,
                "upper": 0.3685952350125229,
                "count": 159,
                "avg_pred": 0.35618661497989723,
                "emp_rate": 0.5786163522012578
            },
            {
                "bin": 3,
                "lower": 0.3687985984125586,
                "upper": 0.37795296781812104,
                "count": 159,
                "avg_pred": 0.3736470291470816,
                "emp_rate": 0.5220125786163522
            },
            {
                "bin": 4,
                "lower": 0.3780270914088168,
                "upper": 0.3805795748671173,
                "count": 159,
                "avg_pred": 0.37964044092193916,
                "emp_rate": 0.29559748427672955
            },
            {
                "bin": 5,
                "lower": 0.3805886707597166,
                "upper": 0.38188127258033705,
                "count": 159,
                "avg_pred": 0.38124903302581875,
                "emp_rate": 0.20754716981132076
            },
            {
                "bin": 6,
                "lower": 0.3818814519349147,
                "upper": 0.5015125444962255,
                "count": 159,
                "avg_pred": 0.4580187196259468,
                "emp_rate": 0.6477987421383647
            },
            {
                "bin": 7,
                "lower": 0.5015136644453851,
                "upper": 0.5027844801181793,
                "count": 159,
                "avg_pred": 0.5021601990880875,
                "emp_rate": 0.9245283018867925
            },
            {
                "bin": 8,
                "lower": 0.5027885255529512,
                "upper": 0.5093533954265327,
                "count": 159,
                "avg_pred": 0.5046095327266781,
                "emp_rate": 0.8930817610062893
            },
            {
                "bin": 9,
                "lower": 0.5095023365433625,
                "upper": 0.606740036116512,
                "count": 159,
                "avg_pred": 0.5361304570300482,
                "emp_rate": 0.6792452830188679
            },
            {
                "bin": 10,
                "lower": 0.607407549807337,
                "upper": 1,
                "count": 150,
                "avg_pred": 0.7583563518880775,
                "emp_rate": 0.6266666666666667
            }
        ]
    },
    "confusion_best": {
        "TP": 937,
        "FP": 620,
        "TN": 22,
        "FN": 2,
        "precision": 0.6017983301220295,
        "recall": 0.9978700745473909,
        "f1": 0.7508012820512819
    },
    "confusion_strict": {
        "TP": 736,
        "FP": 492,
        "TN": 150,
        "FN": 203,
        "precision": 0.5993485342019544,
        "recall": 0.7838125665601704,
        "f1": 0.6792801107521921
    },
    "calibration": {
        "type": "isotonic"
    },
    "hyperparameters": {
        "model_type": "logistic_regression",
        "selected_l2": 0.01,
        "cv_metric": 0.5662736884868061,
        "cv_per_fold": [
            0.5695353498390734,
            0.5673918557461711,
            0.5618938598751739
        ],
        "cv_folds": 3
    },
    "model_type": "logistic_regression",
    "selected_l2": 0.01,
    "cv_metric": 0.5662736884868061,
    "cv_per_fold": [
        0.5695353498390734,
        0.5673918557461711,
        0.5618938598751739
    ],
    "cv_folds": 3
}










# Calibration curve (isotonic)

{
    "thresholds": [
        0,
        0.4839660178503245,
        0.4849427634867383,
        0.4849712677199106,
        0.48498957197708714,
        0.48498996140449574,
        0.48500603114280494,
        0.48500721611987296,
        0.48500990335425015,
        0.4850223087416232,
        0.4850228118162352,
        0.4850380314069179,
        0.48504142106904685,
        0.48508339179737614,
        0.48509029898530465,
        0.48510054384153584,
        0.49994126733292005,
        0.4999541593461248,
        0.49995559727729405,
        0.5054683404316435,
        0.5055124067640978,
        0.5055151067649456,
        0.5055225570199388,
        0.505522610374883,
        0.5055251575293193,
        0.5055316175661967,
        0.505535899662951,
        0.5055359790480791,
        0.5055371574991993,
        0.5055392623636308,
        0.5055404453052623,
        0.5055413556122555,
        0.5055456110220244,
        0.5055471996696272,
        0.5055488220201076,
        0.5055520617874371,
        0.5055597444147413,
        0.5055608414182915,
        0.5055620381179539,
        0.5055630001365821,
        0.505564301415643,
        0.5055687348243391,
        0.5055711016708965,
        0.5055711728017095,
        0.5055712108134268,
        0.5055777301366622,
        0.5055832701818554,
        0.5055833469713016,
        0.5055859064912183,
        0.5055904364323793,
        0.5055918797002174,
        0.505599558048736,
        0.5056000053984625,
        0.5056236425814884,
        0.5056300628281752,
        0.5056350297052575,
        0.5056384516964222,
        0.5056447755670358,
        1
    ],
    "values": [
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0.38235294117647056,
        0.5,
        0.5,
        0.7394736842105263,
        0.9090909090909091,
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
        1,
        1
    ]
}










# Model coefficients (bias, λ, iterations, learning rate, per-feature weights)

{
    "weights": [
        -1.4407728438318017e-6,
        -9.237159460591036e-7,
        -1.3257789078752514e-5,
        6.066712095004125e-6,
        -3.589395585266103e-7,
        2.3557348348415524e-7,
        9.65669727904772e-7,
        -4.825943981660642e-6,
        -7.745663313189273e-8,
        -3.975813442220335e-6,
        5.573280700070313e-7,
        4.135344443000474e-7,
        -3.127591622250378e-7,
        3.0735857972273176e-7,
        4.0120092782702865e-9,
        7.001246912263893e-8,
        -6.795268733212579e-8,
        -2.6550163795886122e-8,
        1.8937216076709705e-8,
        -3.985544455657482e-6,
        1.0612828635850265e-6,
        -1.8051771918443453e-7,
        -3.127591622250378e-7,
        2.6023691690652043e-8,
        2.1626491402966128e-7,
        1.0252493635472216e-6,
        -5.529336215306397e-6,
        3.0735857972273176e-7,
        1.8937216076709705e-8,
        3.5716836450982136e-7,
        3.478420331302359e-7,
        -2.7318945770431903e-7,
        0,
        0,
        0,
        0,
        7.688756015912758e-8,
        -3.6745398037397814e-6,
        -1.4040236722979296e-6,
        -4.942069148492142e-6,
        -4.977791440504509e-6,
        -1.832030785950814e-7,
        -8.928457637430559e-8,
        -4.305449559178826e-8,
        -5.7410118422680285e-6,
        -2.072245300496721e-7,
        1.7641717194933456e-6,
        1.463486959971661e-6,
        -3.6991253781105884e-6,
        -4.942069148492142e-6,
        -3.312447484910889e-7,
        -4.050995188650234e-6,
        3.4580530909931035e-7,
        2.9458080070285126e-6,
        -4.977791440504509e-6,
        -2.072245300496721e-7,
        0,
        1.9893888863068042e-8,
        0,
        0,
        0,
        0,
        0,
        -4.00673077446827e-6,
        8.004790057384792e-6,
        1.5710333806442364e-5,
        1.4768124999228087e-5,
        -1.3257789078752514e-5,
        1.123099453573413e-6,
        5.8538959450661516e-6,
        -1.2005341782675585e-6,
        -7.754647619029187e-7,
        6.066712095004125e-6,
        -7.745663313189273e-8,
        0.00013966944291560115,
        -7.908420391987697e-5,
        -2.6214819884095145e-5,
        4.3104851440243444e-5,
        -4.41724260284719e-6,
        0,
        0,
        6.295113933287666e-6,
        0.00023832902449115294,
        -1.8768106968531073e-6,
        -1.2963819163652163e-6,
        -4.5796426928386417e-7,
        -1.398796388178491e-5,
        6.261980570647131e-6,
        0,
        0,
        0,
        -4.397585507200606e-6,
        -5.046398594592535e-6,
        1.7641156266774348e-6,
        -2.0419303527311274e-6,
        -3.001170943121684e-6,
        -3.122039707164423e-6,
        -1.804432623088966e-7,
        -5.42099305122857e-6,
        -2.620885162509916e-6,
        1.8485369528595233e-6,
        1.9714510083061253e-5,
        4.968208924658502e-6,
        2.4877553185489457e-5,
        2.938500060331293e-5,
        8.813021551309235e-7,
        1.105063944356625e-6,
        3.9193735612207263e-7,
        1.6927802427775687e-5,
        9.351351637845279e-7,
        2.4983095470272138e-5,
        7.393418202922153e-6,
        1.9542697442001897e-5,
        2.4877553185489457e-5,
        4.267065624317586e-6,
        1.948971954948843e-5,
        1.234537235862149e-6,
        1.1456671355332065e-5,
        2.938500060331293e-5,
        9.351351637845279e-7,
        5.603416909324266e-7,
        3.9193735612207263e-7,
        3.388295517740753e-7,
        3.2058136069287155e-8,
        3.633103178126007e-5,
        3.633103178126007e-5,
        3.633103178126007e-5,
        2.4639023955873352e-5,
        1.9714510083061253e-5,
        4.968208924658502e-6,
        2.4877553185489457e-5,
        2.938500060331293e-5,
        8.813021551309235e-7,
        1.105063944356625e-6,
        3.9193735612207263e-7,
        1.6927802427775687e-5,
        9.351351637845279e-7,
        2.4983095470272138e-5,
        7.393418202922153e-6,
        1.9542697442001897e-5,
        2.4877553185489457e-5,
        4.267065624317586e-6,
        1.948971954948843e-5,
        1.234537235862149e-6,
        1.1456671355332065e-5,
        2.938500060331293e-5,
        9.351351637845279e-7,
        5.603416909324266e-7,
        3.9193735612207263e-7,
        3.388295517740753e-7,
        3.2058136069287155e-8,
        3.633103178126007e-5,
        3.633103178126007e-5,
        3.633103178126007e-5,
        2.4639023955873352e-5,
        -3.0518488330662176e-7,
        -4.855976802524693e-6,
        -8.130403065826145e-7,
        1.7641156266774348e-6,
        -5.839881796907327e-6,
        -4.641120770810094e-6,
        -2.249194536324698e-6,
        -4.31340814227094e-6,
        -2.0419303527311274e-6,
        -2.620885162509916e-6,
        -2.582781274584867e-6,
        -1.6407010196182403e-7,
        -3.985520260582416e-7,
        -1.6635236426784095e-7,
        0,
        0,
        0,
        -1.983808379534181e-6,
        4.2944052178055785e-7,
        -1.2391725583321882e-6,
        -3.001170943121684e-6,
        -2.0814774933148518e-6,
        -3.2391108535244556e-6,
        0
    ],
    "bias": -2.683787124704197e-7,
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
        "debt_to_equity": {
            "low": 0,
            "high": 10
        },
        "ln_assets": {
            "low": 10.028444791940512,
            "high": 26.71271335121065
        },
        "EBIT_InterestExpense": {
            "low": -5,
            "high": 10
        },
        "TL_TA": {
            "low": 0.03238451332770474,
            "high": 5
        },
        "ROA": {
            "low": -1,
            "high": 0.18963814235529558
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
            "high": 0.12144938261085439
        },
        "AltmanZPrime": {
            "low": -10,
            "high": 5.2053824402748
        },
        "current_ratio": {
            "low": 0.008923825353307421,
            "high": 5
        },
        "cash_ratio": {
            "low": 0,
            "high": 5
        },
        "WC_TA": {
            "low": -2,
            "high": 0.9421048837475564
        },
        "EBITDA_InterestExpense": {
            "low": -5,
            "high": 10
        },
        "DaysAP": {
            "low": 7.857474831988752,
            "high": 400
        },
        "CFO_DebtService": {
            "low": -2,
            "high": 2
        },
        "Accruals": {
            "low": -2,
            "high": 0.6361494606697984
        },
        "liquidity_accruals": {
            "low": -1.781971371786268,
            "high": 1.2110401520307144
        },
        "CFO_Liabilities": {
            "low": -2,
            "high": 0.826068191627104
        },
        "liquidity_cashflow": {
            "low": -2,
            "high": 2
        },
        "Debt_Assets": {
            "low": 0,
            "high": 1.1987236568714752
        },
        "quick_ratio": {
            "low": 0.00801435584371217,
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
        },
        "cash_ratio_vola": {
            "low": 0.004849779431180028,
            "high": 2
        },
        "debt_to_equity_vola": {
            "low": 0.020544347617637952,
            "high": 2
        },
        "EBIT_InterestExpense_vola": {
            "low": 0.23056153512467686,
            "high": 2
        },
        "CFO_Liabilities_vola": {
            "low": 0.002441467341931833,
            "high": 2
        },
        "TL_TA_vola": {
            "low": 0.006335808244811281,
            "high": 2
        },
        "WC_TA_vola": {
            "low": 0.003666980619815947,
            "high": 2
        },
        "CFO_DebtService_vola": {
            "low": 0.14471702440177267,
            "high": 2
        },
        "ROA_vola": {
            "low": 0.001511420558587202,
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
            "low": 0.0009220159310501493,
            "high": 2
        },
        "net_margin": {
            "low": -1,
            "high": 0.5917584457369137
        },
        "asset_turnover": {
            "low": 0,
            "high": 3.0218684551060178
        },
        "operating_margin": {
            "low": -1,
            "high": 0.7730889457232168
        },
        "OperatingMargin": {
            "low": -1,
            "high": 0.7730889457232168
        },
        "DaysAR": {
            "low": 2.2301828178016527,
            "high": 400
        },
        "size_profitability": {
            "low": -5,
            "high": 5
        },
        "inventory_turnover": {
            "low": 0.018351473156053087,
            "high": 5
        },
        "DaysINV": {
            "low": 2.7559810126582276,
            "high": 400
        },
        "Debt_Assets_tren": {
            "low": -0.7831019558569229,
            "high": 1
        },
        "Debt_Assets_vola": {
            "low": 0.001956920430995195,
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
            "low": 0.013919955535367678,
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
            "low": 0.008735370845804231,
            "high": 2
        },
        "asset_turnover_vola": {
            "low": 0.025557397523865613,
            "high": 2
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
            "low": 0.03410827420850915,
            "high": 2
        },
        "DaysINV_vola": {
            "low": 0.14652732964444937,
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
            "low": 0.1041385008075606,
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
        "current_ratio": 1.640295662856332,
        "quick_ratio": 1.2181551976573939,
        "debt_to_assets": 0.6142431242943835,
        "roa": 0.0021612117453119477,
        "net_margin": 0.033230312621621794,
        "asset_turnover": 0.503707302841848,
        "inventory_turnover": 2.7518948580759592,
        "cash_ratio": 0.3652315692063632,
        "operating_margin": 0.06281943597788078,
        "debt_to_equity": 0.8619952323951587,
        "current_ratio_tren": 0.05781701782186732,
        "quick_ratio_tren": 0.16165825584716875,
        "debt_to_assets_tren": 0.002677596022704648,
        "roa_tren": 0.00388393918475455,
        "net_margin_tren": 0.057648714308199296,
        "asset_turnover_tren": 0.09733804391795763,
        "inventory_turnover_tren": -0.23902263451479608,
        "cash_ratio_tren": 0.03050486738859278,
        "operating_margin_tren": -0.039227271473500636,
        "debt_to_equity_tren": -0.21645152651942215,
        "EBIT_InterestExpense_tren": 0.14613867458376018,
        "CFO_Liabilities_tren": -0.0056093813514252,
        "TL_TA_tren": 0.002677596022704648,
        "Debt_Assets_tren": -0.028962498079705377,
        "WC_TA_tren": 0.007088068123969437,
        "EBITDA_InterestExpense_tren": 1,
        "CFO_DebtService_tren": -0.5326844930704239,
        "ROA_tren": 0.00388393918475455,
        "OperatingMargin_tren": -0.039227271473500636,
        "DaysAR_tren": 1,
        "DaysINV_tren": 1,
        "DaysAP_tren": -1,
        "CashConversionCycle_tren": -1,
        "DividendOmission_tren": 0,
        "DebtIssuanceSpike_tren": 0,
        "DebtRepaymentSpike_tren": 0,
        "Accruals_tren": 0.002146861683311952,
        "current_ratio_vola": 0.8641321415010376,
        "quick_ratio_vola": 0.5383719286259836,
        "debt_to_assets_vola": 0.20812975071859807,
        "roa_vola": 0.05138404172402002,
        "net_margin_vola": 0.09442939717175104,
        "asset_turnover_vola": 0.29102124676793073,
        "inventory_turnover_vola": 0.9081043318948339,
        "cash_ratio_vola": 0.36344765171658033,
        "operating_margin_vola": 0.08685341216640921,
        "debt_to_equity_vola": 1.2776896193856415,
        "EBIT_InterestExpense_vola": 2,
        "CFO_Liabilities_vola": 0.09410619408165609,
        "TL_TA_vola": 0.20812975071859807,
        "Debt_Assets_vola": 0.10712772258913805,
        "WC_TA_vola": 0.20118195616249937,
        "EBITDA_InterestExpense_vola": 2,
        "CFO_DebtService_vola": 2,
        "ROA_vola": 0.05138404172402002,
        "OperatingMargin_vola": 0.08685341216640921,
        "DaysAR_vola": 2,
        "DaysINV_vola": 2,
        "DaysAP_vola": 2,
        "CashConversionCycle_vola": 2,
        "DividendOmission_vola": 0,
        "DebtIssuanceSpike_vola": 0,
        "DebtRepaymentSpike_vola": 0,
        "Accruals_vola": 0.0360916068538877,
        "ln_assets": 20.360486585288182,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "TL_TA": 0.6142431242943835,
        "Debt_Assets": 0.2230047359916314,
        "WC_TA": 0.11865402294420357,
        "EBITDA_InterestExpense": 3.122072614554604,
        "CFO_DebtService": 1.9998838289962826,
        "ROA": 0.0021612117453119477,
        "OperatingMargin": 0.06281943597788078,
        "DaysAR": 62.63587074580751,
        "DaysINV": 127.76267834380778,
        "DaysAP": 73.49464291082673,
        "CashConversionCycle": 109.34374767109362,
        "DividendOmission": 0,
        "DebtIssuanceSpike": 0,
        "DebtRepaymentSpike": 0,
        "Accruals": -0.02874248928712076,
        "leverage_profitability": 4.220714343349934e-6,
        "liquidity_cashflow": -5.047645662640472e-7,
        "size_profitability": 0.7002069157151501,
        "leverage_margin": 0.04163998289160741,
        "liquidity_accruals": -0.048112476159634536,
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
        "current_ratio": 1.640295662856332,
        "quick_ratio": 1.2181551976573939,
        "debt_to_assets": 0.6142431242943835,
        "roa": 0.0021612117453119477,
        "net_margin": 0.033230312621621794,
        "asset_turnover": 0.503707302841848,
        "inventory_turnover": 2.7518948580759592,
        "cash_ratio": 0.3652315692063632,
        "operating_margin": 0.06281943597788078,
        "debt_to_equity": 0.8619952323951587,
        "current_ratio_tren": 0.05781701782186732,
        "quick_ratio_tren": 0.16165825584716875,
        "debt_to_assets_tren": 0.002677596022704648,
        "roa_tren": 0.00388393918475455,
        "net_margin_tren": 0.057648714308199296,
        "asset_turnover_tren": 0.09733804391795763,
        "inventory_turnover_tren": -0.23902263451479608,
        "cash_ratio_tren": 0.03050486738859278,
        "operating_margin_tren": -0.039227271473500636,
        "debt_to_equity_tren": -0.21645152651942215,
        "EBIT_InterestExpense_tren": 0.14613867458376018,
        "CFO_Liabilities_tren": -0.0056093813514252,
        "TL_TA_tren": 0.002677596022704648,
        "Debt_Assets_tren": -0.028962498079705377,
        "WC_TA_tren": 0.007088068123969437,
        "EBITDA_InterestExpense_tren": 1,
        "CFO_DebtService_tren": -0.5326844930704239,
        "ROA_tren": 0.00388393918475455,
        "OperatingMargin_tren": -0.039227271473500636,
        "DaysAR_tren": 1,
        "DaysINV_tren": 1,
        "DaysAP_tren": -1,
        "CashConversionCycle_tren": -1,
        "DividendOmission_tren": 0,
        "DebtIssuanceSpike_tren": 0,
        "DebtRepaymentSpike_tren": 0,
        "Accruals_tren": 0.002146861683311952,
        "current_ratio_vola": 0.8641321415010376,
        "quick_ratio_vola": 0.5383719286259836,
        "debt_to_assets_vola": 0.20812975071859807,
        "roa_vola": 0.05138404172402002,
        "net_margin_vola": 0.09442939717175104,
        "asset_turnover_vola": 0.29102124676793073,
        "inventory_turnover_vola": 0.9081043318948339,
        "cash_ratio_vola": 0.36344765171658033,
        "operating_margin_vola": 0.08685341216640921,
        "debt_to_equity_vola": 1.2776896193856415,
        "EBIT_InterestExpense_vola": 2,
        "CFO_Liabilities_vola": 0.09410619408165609,
        "TL_TA_vola": 0.20812975071859807,
        "Debt_Assets_vola": 0.10712772258913805,
        "WC_TA_vola": 0.20118195616249937,
        "EBITDA_InterestExpense_vola": 2,
        "CFO_DebtService_vola": 2,
        "ROA_vola": 0.05138404172402002,
        "OperatingMargin_vola": 0.08685341216640921,
        "DaysAR_vola": 2,
        "DaysINV_vola": 2,
        "DaysAP_vola": 2,
        "CashConversionCycle_vola": 2,
        "DividendOmission_vola": 0,
        "DebtIssuanceSpike_vola": 0,
        "DebtRepaymentSpike_vola": 0,
        "Accruals_vola": 0.0360916068538877,
        "ln_assets": 20.360486585288182,
        "EBIT_InterestExpense": 1.3399509466544868,
        "CFO_Liabilities": 0.010959979797706286,
        "TL_TA": 0.6142431242943835,
        "Debt_Assets": 0.2230047359916314,
        "WC_TA": 0.11865402294420357,
        "EBITDA_InterestExpense": 3.122072614554604,
        "CFO_DebtService": 1.9998838289962826,
        "ROA": 0.0021612117453119477,
        "OperatingMargin": 0.06281943597788078,
        "DaysAR": 62.63587074580751,
        "DaysINV": 127.76267834380778,
        "DaysAP": 73.49464291082673,
        "CashConversionCycle": 109.34374767109362,
        "DividendOmission": 0,
        "DebtIssuanceSpike": 0,
        "DebtRepaymentSpike": 0,
        "Accruals": -0.02874248928712076,
        "leverage_profitability": 4.220714343349934e-6,
        "liquidity_cashflow": -5.047645662640472e-7,
        "size_profitability": 0.7002069157151501,
        "leverage_margin": 0.04163998289160741,
        "liquidity_accruals": -0.048112476159634536,
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
        "current_ratio": 1.3827459050619193,
        "quick_ratio": 1,
        "debt_to_assets": 0.42126595758989516,
        "roa": 0.08231505469191362,
        "net_margin": 1,
        "asset_turnover": 1,
        "inventory_turnover": 1,
        "cash_ratio": 0.5142925778860005,
        "operating_margin": 1,
        "debt_to_equity": 1.7017198353593066,
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
        "ln_assets": 4.702217012571836,
        "EBIT_InterestExpense": 1,
        "CFO_Liabilities": 0.07649589798301172,
        "TL_TA": 0.42126595758989516,
        "Debt_Assets": 1,
        "WC_TA": 0.2692590139361537,
        "EBITDA_InterestExpense": 1,
        "CFO_DebtService": 0.6035212037022586,
        "ROA": 0.08231505469191362,
        "OperatingMargin": 1,
        "DaysAR": 1,
        "DaysINV": 1,
        "DaysAP": 1,
        "CashConversionCycle": 1,
        "DividendOmission": 1,
        "DebtIssuanceSpike": 1,
        "DebtRepaymentSpike": 1,
        "Accruals": 0.053339372230789196,
        "leverage_profitability": 0.020329325371956163,
        "liquidity_cashflow": 0.00016642487687652138,
        "size_profitability": 1,
        "leverage_margin": 1,
        "liquidity_accruals": 0.050013717724775,
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