# General Summary

Logistic regression bankruptcy classifier trained on grouped annual filings (validation year 2023, test year 2024) with inverse-frequency class weighting and isotonic calibration. Latest model snapshot: 2025-10-17T13:10:55+02:00.

    Optimisation target: precision-recall AUC (PR AUC = 0.7045, ROC AUC = 0.5149, Brier score = 0.2459).
    Calibration thresholds (isotonic): probability grid [0, 0, 0, 0, 0, 0, 1, 1, 1, 1] -> calibrated scores [0.4625, 0.4787, 0.5102, 0.5254, 0.5414, 0.5556, 0.5714, 0.5902, 0.6, 0.7586].
    "Best" decision point yields TP=479, FP=318, TN=324, FN=460 (precision 0.601, recall 0.5101, F1 0.5518), strict recall at 0.8 uses threshold 0.47.
    L2 regularisation λ=0.001, 200 gradient-descent epochs, learning rate 0.1. Bias = -0.0126.










# Training metadata & feature order

{
    "timestamp": "2025-10-17T13:10:55+02:00",
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
                "lower": 0.01,
                "upper": 0.99
            },
            "impute_strategy": "median",
            "scale": "robust"
        },
        "class_weighting": "inverse_frequency",
        "model": {
            "type": "logistic_regression",
            "l2": 1,
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
        "debt_to_equity",
        "net_margin",
        "roa",
        "roe",
        "asset_turnover",
        "inventory_turnover",
        "cash_ratio",
        "operating_margin",
        "revenues_leve",
        "OperatingIncomeLoss_leve",
        "NetIncomeLoss_leve",
        "assets_leve",
        "revenues_tren",
        "OperatingIncomeLoss_tren",
        "NetIncomeLoss_tren",
        "assets_tren",
        "revenues_vola",
        "OperatingIncomeLoss_vola",
        "NetIncomeLoss_vola",
        "assets_vola",
        "ln_assets",
        "ln_revenues",
        "DividendOmission",
        "DebtIssuanceSpike",
        "DebtRepaymentSpike",
        "TL_TA",
        "Debt_Assets",
        "EBIT_InterestExpense",
        "EBITDA_InterestExpense",
        "CFO_Liabilities",
        "CFO_DebtService",
        "WC_TA",
        "CurrentRatio",
        "QuickRatio",
        "ROA",
        "OperatingMargin",
        "DaysAR",
        "DaysINV",
        "DaysAP",
        "CashConversionCycle",
        "Accruals",
        "assets",
        "CurrentAssets",
        "NoncurrentAssets",
        "liabilities",
        "CurrentLiabilities",
        "NoncurrentLiabilities",
        "LiabilitiesAndStockholdersEquity",
        "equity",
        "CommonStockValue",
        "RetainedEarningsAccumulatedDeficit",
        "AccumulatedOtherComprehensiveIncomeLoss",
        "MinorityInterest",
        "revenues",
        "SalesRevenueNet",
        "CostOfGoodsSold",
        "GrossProfit",
        "OperatingExpenses",
        "SellingGeneralAndAdministrativeExpense",
        "ResearchAndDevelopmentExpense",
        "OperatingIncomeLoss",
        "InterestExpense",
        "IncomeBeforeIncomeTaxes",
        "IncomeTaxExpenseBenefit",
        "NetIncomeLoss",
        "PreferredStockDividendsAndOtherAdjustments",
        "NetIncomeLossAvailableToCommonStockholdersBasic",
        "EarningsPerShareBasic",
        "EarningsPerShareDiluted",
        "WeightedAverageNumberOfSharesOutstandingBasic",
        "WeightedAverageNumberOfDilutedSharesOutstanding",
        "NetCashProvidedByUsedInOperatingActivities",
        "NetCashProvidedByUsedInInvestingActivities",
        "NetCashProvidedByUsedInFinancingActivities",
        "CashAndCashEquivalentsPeriodIncreaseDecrease",
        "CashAndCashEquivalentsAtCarryingValue",
        "PaymentsToAcquirePropertyPlantAndEquipment",
        "ProceedsFromIssuanceOfCommonStock",
        "PaymentsOfDividends",
        "RepaymentsOfDebt",
        "ProceedsFromIssuanceOfDebt",
        "DepreciationAndAmortization",
        "InventoryNet",
        "AccountsReceivableNetCurrent",
        "AccountsPayableCurrent",
        "Goodwill",
        "IntangibleAssetsNetExcludingGoodwill",
        "PropertyPlantAndEquipmentNet",
        "LongTermDebtNoncurrent",
        "ShortTermBorrowings",
        "IncomeTaxesPayableCurrent",
        "AltmanZPrime",
        "AltmanZDoublePrime",
        "OhlsonOScore",
        "OhlsonOScoreProb",
        "ZmijewskiXScore",
        "SpringateSScore",
        "TafflerZScore",
        "FulmerHScore",
        "GroverGScore",
        "BeneishMScore",
        "PiotroskiFScore",
        "company_id_missing",
        "fiscal_year_missing",
        "label_missing",
        "current_ratio_missing",
        "quick_ratio_missing",
        "debt_to_assets_missing",
        "debt_to_equity_missing",
        "net_margin_missing",
        "roa_missing",
        "roe_missing",
        "asset_turnover_missing",
        "inventory_turnover_missing",
        "cash_ratio_missing",
        "operating_margin_missing",
        "revenues_leve_missing",
        "OperatingIncomeLoss_leve_missing",
        "NetIncomeLoss_leve_missing",
        "assets_leve_missing",
        "revenues_tren_missing",
        "OperatingIncomeLoss_tren_missing",
        "NetIncomeLoss_tren_missing",
        "assets_tren_missing",
        "revenues_vola_missing",
        "OperatingIncomeLoss_vola_missing",
        "NetIncomeLoss_vola_missing",
        "assets_vola_missing",
        "ln_assets_missing",
        "ln_revenues_missing",
        "DividendOmission_missing",
        "DebtIssuanceSpike_missing",
        "DebtRepaymentSpike_missing",
        "TL_TA_missing",
        "Debt_Assets_missing",
        "EBIT_InterestExpense_missing",
        "EBITDA_InterestExpense_missing",
        "CFO_Liabilities_missing",
        "CFO_DebtService_missing",
        "WC_TA_missing",
        "CurrentRatio_missing",
        "QuickRatio_missing",
        "ROA_missing",
        "OperatingMargin_missing",
        "DaysAR_missing",
        "DaysINV_missing",
        "DaysAP_missing",
        "CashConversionCycle_missing",
        "Accruals_missing",
        "assets_missing",
        "CurrentAssets_missing",
        "NoncurrentAssets_missing",
        "liabilities_missing",
        "CurrentLiabilities_missing",
        "NoncurrentLiabilities_missing",
        "LiabilitiesAndStockholdersEquity_missing",
        "equity_missing",
        "CommonStockValue_missing",
        "RetainedEarningsAccumulatedDeficit_missing",
        "AccumulatedOtherComprehensiveIncomeLoss_missing",
        "MinorityInterest_missing",
        "revenues_missing",
        "SalesRevenueNet_missing",
        "CostOfGoodsSold_missing",
        "GrossProfit_missing",
        "OperatingExpenses_missing",
        "SellingGeneralAndAdministrativeExpense_missing",
        "ResearchAndDevelopmentExpense_missing",
        "OperatingIncomeLoss_missing",
        "InterestExpense_missing",
        "IncomeBeforeIncomeTaxes_missing",
        "IncomeTaxExpenseBenefit_missing",
        "NetIncomeLoss_missing",
        "PreferredStockDividendsAndOtherAdjustments_missing",
        "NetIncomeLossAvailableToCommonStockholdersBasic_missing",
        "EarningsPerShareBasic_missing",
        "EarningsPerShareDiluted_missing",
        "WeightedAverageNumberOfSharesOutstandingBasic_missing",
        "WeightedAverageNumberOfDilutedSharesOutstanding_missing",
        "NetCashProvidedByUsedInOperatingActivities_missing",
        "NetCashProvidedByUsedInInvestingActivities_missing",
        "NetCashProvidedByUsedInFinancingActivities_missing",
        "CashAndCashEquivalentsPeriodIncreaseDecrease_missing",
        "CashAndCashEquivalentsAtCarryingValue_missing",
        "PaymentsToAcquirePropertyPlantAndEquipment_missing",
        "ProceedsFromIssuanceOfCommonStock_missing",
        "PaymentsOfDividends_missing",
        "RepaymentsOfDebt_missing",
        "ProceedsFromIssuanceOfDebt_missing",
        "DepreciationAndAmortization_missing",
        "InventoryNet_missing",
        "AccountsReceivableNetCurrent_missing",
        "AccountsPayableCurrent_missing",
        "Goodwill_missing",
        "IntangibleAssetsNetExcludingGoodwill_missing",
        "PropertyPlantAndEquipmentNet_missing",
        "LongTermDebtNoncurrent_missing",
        "ShortTermBorrowings_missing",
        "IncomeTaxesPayableCurrent_missing",
        "EntityIncorporationStateCountryCode_missing",
        "EntityFilerCategory_missing",
        "AltmanZPrime_missing",
        "AltmanZDoublePrime_missing",
        "OhlsonOScore_missing",
        "OhlsonOScoreProb_missing",
        "ZmijewskiXScore_missing",
        "SpringateSScore_missing",
        "TafflerZScore_missing",
        "FulmerHScore_missing",
        "GroverGScore_missing",
        "BeneishMScore_missing",
        "PiotroskiFScore_missing",
        "EntityIncorporationStateCountryCode_0",
        "EntityFilerCategory_0"
    ]
}










# Evaluation metrics (validation/test)

{
    "pr_auc": 0.7044626886908341,
    "roc_auc": 0.51491943109094,
    "brier": 0.24592486070567904,
    "thresholds": {
        "best": 0.5714285714285714,
        "recall80": 0.47
    },
    "confusion_best": {
        "TP": 479,
        "FP": 318,
        "TN": 324,
        "FN": 460,
        "precision": 0.6010037641154329,
        "recall": 0.5101171458998935,
        "f1": 0.5518433179723503
    },
    "confusion_strict": {
        "TP": 939,
        "FP": 642,
        "TN": 0,
        "FN": 0,
        "precision": 0.5939278937381404,
        "recall": 1,
        "f1": 0.7452380952380953
    }
}










# Calibration curve (isotonic)

{
    "thresholds": [
        0,
        0,
        0,
        0,
        0,
        0,
        1,
        1,
        1,
        1
    ],
    "values": [
        0.4625,
        0.4787234042553192,
        0.5102040816326531,
        0.5254237288135594,
        0.5413533834586466,
        0.5555555555555556,
        0.5714285714285714,
        0.5901639344262295,
        0.6,
        0.7586206896551724
    ]
}










# Model coefficients (bias, λ, iterations, learning rate, per-feature weights)

{
    "weights": [
        -0.020420344249475197,
        0.01595168495228093,
        -0.5920448791570021,
        -0.030546887031658056,
        -0.19380095860343274,
        0.19865211522629786,
        -0.11294915159996845,
        -0.0016397345143040868,
        -0.02032922545528084,
        -0.05945768407596431,
        -0.17260945109903866,
        -17599345.330589145,
        0.11738985647885647,
        -0.01341585272026514,
        -0.3784117100082987,
        70027151.17965338,
        13619852.045658644,
        10971467.841456806,
        -145946217.09369123,
        -136518843.7796356,
        -14267787.87429304,
        -14262222.785632752,
        -832044878.138137,
        0.008905381276157996,
        -0.059154141056285796,
        -0.00046613237983201834,
        0,
        0,
        -0.30254400609557863,
        0.010756657582726977,
        -3.199217082106035,
        -2.441762710520648,
        -0.025162018791311927,
        -0.8074677553491733,
        0.24367797799618335,
        -0.019876853068594127,
        -0.020634726548268076,
        0.16996809361871493,
        -0.1626097885649609,
        2.361260762469343,
        -3.9902370701062724,
        -0.7088188263800768,
        1.1368959125797307,
        0.03357487679974461,
        -0.3784117100082987,
        0.056277292703465875,
        -16020897.165363539,
        -1.5220075868397354,
        0.09240313729648972,
        11808406.501131365,
        0.0069005117134399545,
        0.024647685467326368,
        4.551129421886102,
        0.1914778996995868,
        0,
        3088755.0893100733,
        -17599345.330589145,
        1380741.5461582385,
        -6121548.4199830545,
        -6947776.241427099,
        11172880.10008081,
        -2294594.7744964017,
        -5256071.821195374,
        0.11738985647885647,
        -1.6425629076204895,
        0,
        0.11422740881622744,
        -0.01341585272026514,
        -1725454.6440323049,
        616475.0522204624,
        -0.016999766797500218,
        -0.018188766740073584,
        0.12479047711552677,
        0.12044737747935655,
        0.14118385067632463,
        -0.15179311701884873,
        0.17044147400436452,
        441392.7690906451,
        0.023470909016236798,
        0.35759618609476795,
        -148041.16921891097,
        -971543.81336707,
        -1870965.3130015053,
        2682759.26759121,
        1120950.359671435,
        5064296.877069825,
        1.1739118658635646,
        -0.0536176247330242,
        -0.08703174000226155,
        3511343.711947058,
        0.19745636048589665,
        18972310.164035365,
        -93674333.72914442,
        0,
        0.5304618671573739,
        0.8264216243935393,
        -1.3298602061143168,
        -0.6342135257456767,
        -0.34993245316001154,
        0.40487632354646336,
        -1.5539844810376267,
        0.10547302258619096,
        0.37426938264034904,
        0.20118987921873802,
        0.00939404793850397,
        0,
        0,
        0,
        -0.01614964104020219,
        -0.01182524017427883,
        -0.0019979659299245596,
        -0.002032829894275566,
        -0.012447260465834678,
        -0.009576549595985367,
        -0.007964711872299728,
        -0.012386617805726443,
        0.0011012414764583337,
        -0.019969578497098225,
        -0.010839044559981017,
        -0.006853874641655687,
        -0.015008397939001073,
        -0.0085426692837312,
        -0.0013234329599554886,
        0.1886196094602023,
        0.1886196094602023,
        0.1886196094602023,
        0.1886196094602023,
        0.1886196094602023,
        0.1886196094602023,
        0.1886196094602023,
        0.1886196094602023,
        -0.0017721623326965494,
        -0.006305357183524864,
        0,
        0,
        0,
        -0.0017721623326965494,
        -0.0017721623326965494,
        -0.02451958423244335,
        -0.02451958423244335,
        -0.0009180532997678985,
        -0.023675483510764553,
        -0.0017721623326965494,
        -0.015859226692420843,
        -0.015859226692420843,
        -0.0017721623326965494,
        -0.012699800633759927,
        -0.012699800633759927,
        -0.0013163332405291656,
        -0.0013163332405291656,
        -0.0022364592370360554,
        -0.0047023959328334275,
        -0.0013234329599554886,
        -0.01524440827199484,
        0.004967130149293333,
        -0.0010189182110084123,
        -0.01570600027223261,
        -0.00022648214291558108,
        -0.01116588671009054,
        -0.009909359333419372,
        -0.02836955141130566,
        -0.010377536767529873,
        0,
        0.0006118381879400489,
        -0.006853874641655687,
        -0.012386617805726443,
        -0.0013962071932842178,
        -0.008670946487501968,
        -0.011416305606184275,
        -0.01229988316950475,
        -0.014970778255856387,
        -0.015008397939001073,
        -0.025380868211561082,
        0,
        -0.014137177542912775,
        -0.0085426692837312,
        -0.0006316636933310499,
        -0.009520737779879163,
        -0.010323885533151578,
        -0.012104508598226342,
        -0.013687666626663366,
        -0.012867394819038095,
        -0.008729593151083804,
        -0.013124364727085292,
        -0.01065909199123176,
        -0.0017818351190148681,
        -0.017374128308371907,
        -0.03077162481542314,
        -0.005333393778380879,
        0.009439947528035203,
        -0.00621577068587822,
        -0.0030410496237363214,
        -0.014563244298624446,
        -0.011300958806463614,
        -0.028712381357809253,
        -0.020163031828586944,
        -0.017815612101287553,
        -0.009155909149146834,
        -0.021802924951995956,
        -0.022660635769298332,
        0.0006751914948311575,
        0,
        0,
        0,
        -0.0017721623326965494,
        -0.0017721623326965494,
        -0.015995561270560184,
        -0.015995561270560184,
        0,
        0,
        0,
        -0.005465765054777947,
        0,
        -0.0035623869567719653,
        0,
        -0.012628560074509599,
        -0.012628560074509599
    ],
    "bias": -0.012628561635092152,
    "lambda": 0.001,
    "iterations": 200,
    "learningRate": 0.1,
    "maxGradNorm": null,
    "earlyStoppingPatience": 5,
    "earlyStoppingMinDelta": 0.0001
}










# Preprocessing pipeline

Pipeline order: winsorisation -> median imputation (+missingness flags) -> robust scaling -> one-hot encoding.





# Winsorizer parameters & cutoffs

{
    "lower": 0.01,
    "upper": 0.99,
    "cutoffs": {
        "company_id": {
            "low": 106,
            "high": 10756
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
            "low": 0.012722223450606128,
            "high": 93.50240963855421
        },
        "debt_to_equity": {
            "low": -31.89976233208105,
            "high": 61.837920489296636
        },
        "roa": {
            "low": -17.548636548636548,
            "high": 0.324455205811138
        },
        "roe": {
            "low": -4.475770925110132,
            "high": 5.246812674706333
        },
        "revenues_leve": {
            "low": 0,
            "high": 153566000000
        },
        "OperatingIncomeLoss_leve": {
            "low": -803429000,
            "high": 6539000000
        },
        "NetIncomeLoss_leve": {
            "low": -911335000,
            "high": 10550657000
        },
        "assets_leve": {
            "low": 1765,
            "high": 1523502000000
        },
        "ln_assets": {
            "low": 8.878079256126435,
            "high": 28.052032748137968
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
        "TL_TA": {
            "low": 0,
            "high": 72.577375861549
        },
        "Debt_Assets": {
            "low": 0,
            "high": 1.0226210018873
        },
        "EBIT_InterestExpense": {
            "low": -2784.047824116,
            "high": 841.91228070175
        },
        "EBITDA_InterestExpense": {
            "low": -2692,
            "high": 933.29333333333
        },
        "CFO_Liabilities": {
            "low": -4.5772283716866,
            "high": 1.5427907896037
        },
        "CFO_DebtService": {
            "low": -1774.4078762307,
            "high": 749.0979020979
        },
        "WC_TA": {
            "low": -50.646596858639,
            "high": 1
        },
        "ROA": {
            "low": -14.455629139073,
            "high": 0.30585096210471
        },
        "assets": {
            "low": 1765,
            "high": 1523502000000
        },
        "liabilities": {
            "low": 14000,
            "high": 1757958000000
        },
        "LiabilitiesAndStockholdersEquity": {
            "low": 1800,
            "high": 851733000000
        },
        "equity": {
            "low": -582651000,
            "high": 72208000000
        },
        "CommonStockValue": {
            "low": 0,
            "high": 5061000000
        },
        "RetainedEarningsAccumulatedDeficit": {
            "low": -8110000000,
            "high": 63443000000
        },
        "revenues": {
            "low": 0,
            "high": 153566000000
        },
        "CostOfGoodsSold": {
            "low": 0,
            "high": 76726000000
        },
        "GrossProfit": {
            "low": -4862000,
            "high": 27715000000
        },
        "OperatingIncomeLoss": {
            "low": -803429000,
            "high": 6539000000
        },
        "InterestExpense": {
            "low": -3923,
            "high": 46559584000
        },
        "NetIncomeLoss": {
            "low": -911335000,
            "high": 10550657000
        },
        "EarningsPerShareBasic": {
            "low": -17.52,
            "high": 19.13
        },
        "WeightedAverageNumberOfSharesOutstandingBasic": {
            "low": 9523,
            "high": 3234000000
        },
        "CashAndCashEquivalentsPeriodIncreaseDecrease": {
            "low": -2260000000,
            "high": 2768000000
        },
        "CashAndCashEquivalentsAtCarryingValue": {
            "low": 0,
            "high": 13035000000
        },
        "ProceedsFromIssuanceOfCommonStock": {
            "low": -815000,
            "high": 1803000000
        },
        "AccountsPayableCurrent": {
            "low": 1205,
            "high": 10667000000
        },
        "AltmanZPrime": {
            "low": -575.79527409845,
            "high": 10.605090256929
        },
        "AltmanZDoublePrime": {
            "low": -2431.4670914389,
            "high": 42.128395765168
        },
        "ZmijewskiXScore": {
            "low": -4.8542355837558,
            "high": 481.42951121533
        },
        "SpringateSScore": {
            "low": -111.11641578255,
            "high": 2.0666425797025
        },
        "TafflerZScore": {
            "low": -8824.8592373606,
            "high": 3158.3390120553
        },
        "GroverGScore": {
            "low": -146.80035564854,
            "high": 2.3914180050502
        },
        "PiotroskiFScore": {
            "low": 0,
            "high": 7
        },
        "current_ratio": {
            "low": 0.0024785957230540677,
            "high": 32.42579752088166
        },
        "cash_ratio": {
            "low": 0,
            "high": 24.898075428031888
        },
        "ln_revenues": {
            "low": 7.718685495198466,
            "high": 25.780192938821944
        },
        "CurrentRatio": {
            "low": 7.1246945905823e-5,
            "high": 32.425797520882
        },
        "QuickRatio": {
            "low": 0,
            "high": 31.664068209501
        },
        "DaysINV": {
            "low": 0,
            "high": 9661.2345679012
        },
        "DaysAP": {
            "low": 0,
            "high": 11638.119592464
        },
        "Accruals": {
            "low": -4.26241878013,
            "high": 0.73131563658219
        },
        "CurrentAssets": {
            "low": 664,
            "high": 37084000000
        },
        "CurrentLiabilities": {
            "low": 13557,
            "high": 27881000000
        },
        "PaymentsToAcquirePropertyPlantAndEquipment": {
            "low": 0,
            "high": 7584000000
        },
        "DepreciationAndAmortization": {
            "low": 0,
            "high": 2891000000
        },
        "AccountsReceivableNetCurrent": {
            "low": 0,
            "high": 8668000000
        },
        "PropertyPlantAndEquipmentNet": {
            "low": 168,
            "high": 43894000000
        },
        "OhlsonOScore": {
            "low": -13.077656293181,
            "high": 906.99623670491
        },
        "OhlsonOScoreProb": {
            "low": 2.091438639306e-6,
            "high": 1
        },
        "BeneishMScore": {
            "low": -85.421522030742,
            "high": 16.611152818693
        },
        "ResearchAndDevelopmentExpense": {
            "low": 0,
            "high": 6980962000
        },
        "NetCashProvidedByUsedInOperatingActivities": {
            "low": -355254000,
            "high": 12197000000
        },
        "NetCashProvidedByUsedInInvestingActivities": {
            "low": -18820000000,
            "high": 1670000000
        },
        "NetCashProvidedByUsedInFinancingActivities": {
            "low": -7431000000,
            "high": 10729000000
        },
        "IntangibleAssetsNetExcludingGoodwill": {
            "low": 0,
            "high": 20560000000
        },
        "LongTermDebtNoncurrent": {
            "low": 0,
            "high": 40370000000
        },
        "IncomeTaxExpenseBenefit": {
            "low": -538000000,
            "high": 3732000000
        },
        "EarningsPerShareDiluted": {
            "low": -16.65,
            "high": 19.71
        },
        "WeightedAverageNumberOfDilutedSharesOutstanding": {
            "low": 8327,
            "high": 3234000000
        },
        "ProceedsFromIssuanceOfDebt": {
            "low": 0,
            "high": 34182000000
        },
        "quick_ratio": {
            "low": -0.11769548199329762,
            "high": 16.339996377587145
        },
        "revenues_tren": {
            "low": -68424882968,
            "high": 38285100000
        },
        "OperatingIncomeLoss_tren": {
            "low": -5093239000,
            "high": 5176500000
        },
        "NetIncomeLoss_tren": {
            "low": -6026321000,
            "high": 8477422466
        },
        "assets_tren": {
            "low": -332722703569,
            "high": 2760323874000
        },
        "revenues_vola": {
            "low": 0,
            "high": 72793742692.5
        },
        "OperatingIncomeLoss_vola": {
            "low": 0,
            "high": 4202502750
        },
        "NetIncomeLoss_vola": {
            "low": 0,
            "high": 8162193641.5
        },
        "assets_vola": {
            "low": 229555,
            "high": 1380161937000
        },
        "NoncurrentAssets": {
            "low": 0,
            "high": 135754000000
        },
        "NoncurrentLiabilities": {
            "low": 0,
            "high": 44636000000
        },
        "MinorityInterest": {
            "low": -51000000,
            "high": 8045000000
        },
        "NetIncomeLossAvailableToCommonStockholdersBasic": {
            "low": -1914699000,
            "high": 11184141000
        },
        "PaymentsOfDividends": {
            "low": 0,
            "high": 5304000000
        },
        "RepaymentsOfDebt": {
            "low": -2774,
            "high": 29866052000
        },
        "InventoryNet": {
            "low": 0,
            "high": 9565000000
        },
        "Goodwill": {
            "low": 0,
            "high": 52242000000
        },
        "ShortTermBorrowings": {
            "low": 0,
            "high": 202389000000
        },
        "FulmerHScore": {
            "low": -10.207523249691,
            "high": 701.81144166429
        },
        "net_margin": {
            "low": -171.6640625,
            "high": 1.043265920090644
        },
        "asset_turnover": {
            "low": 0,
            "high": 3.8159052203015262
        },
        "operating_margin": {
            "low": -167.5703125,
            "high": 1.0435103244837758
        },
        "OperatingMargin": {
            "low": -143.49508196721,
            "high": 1.2237776425238
        },
        "DaysAR": {
            "low": 0,
            "high": 1293.8278595696
        },
        "SalesRevenueNet": {
            "low": 0,
            "high": 85800000000
        },
        "OperatingExpenses": {
            "low": 99,
            "high": 13373700000
        },
        "SellingGeneralAndAdministrativeExpense": {
            "low": 11924,
            "high": 20869000000
        },
        "inventory_turnover": {
            "low": 0,
            "high": 132.43922883487008
        },
        "PreferredStockDividendsAndOtherAdjustments": {
            "low": -379000,
            "high": 8869000000
        },
        "CashConversionCycle": {
            "low": -6826.2411189164,
            "high": 4462.684795595
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
        "debt_to_equity": 0.8619952323951587,
        "net_margin": 0.033230312621621794,
        "roa": 0.0021612117453119477,
        "roe": 0.02234545616458824,
        "asset_turnover": 0.503707302841848,
        "inventory_turnover": 2.7518948580759592,
        "cash_ratio": 0.3652315692063632,
        "operating_margin": 0.06281943597788078,
        "revenues_leve": 250176000,
        "OperatingIncomeLoss_leve": 45085,
        "NetIncomeLoss_leve": 270815,
        "assets_leve": 683980000,
        "revenues_tren": 0,
        "OperatingIncomeLoss_tren": 0,
        "NetIncomeLoss_tren": 0,
        "assets_tren": -26019641,
        "revenues_vola": 3668535.3881730684,
        "OperatingIncomeLoss_vola": 27952284.613694817,
        "NetIncomeLoss_vola": 37433444.5,
        "assets_vola": 1414201895.1112053,
        "ln_assets": 20.360486585288182,
        "ln_revenues": 19.74753025580043,
        "DividendOmission": 0,
        "DebtIssuanceSpike": 0,
        "DebtRepaymentSpike": 0,
        "TL_TA": 0.50882425975121,
        "Debt_Assets": 0,
        "EBIT_InterestExpense": 1.76223219864155,
        "EBITDA_InterestExpense": 2.27818979416885,
        "CFO_Liabilities": 0,
        "CFO_DebtService": 0.96278511404562,
        "WC_TA": 0.016008192374346,
        "CurrentRatio": 1.63007353396535,
        "QuickRatio": 1.30180978664725,
        "ROA": 0,
        "OperatingMargin": 0.068691335213895,
        "DaysAR": 46.261775587273,
        "DaysINV": 103.98379588132,
        "DaysAP": 57.419165427274,
        "CashConversionCycle": 85.3368190463265,
        "Accruals": -0.02697341846178,
        "assets": 683980000,
        "CurrentAssets": 133894000,
        "NoncurrentAssets": 65680000,
        "liabilities": 226151000,
        "CurrentLiabilities": 52323000,
        "NoncurrentLiabilities": 58800000,
        "LiabilitiesAndStockholdersEquity": 668790000,
        "equity": 131891500,
        "CommonStockValue": 218000,
        "RetainedEarningsAccumulatedDeficit": -839670,
        "AccumulatedOtherComprehensiveIncomeLoss": 0,
        "MinorityInterest": 9505000,
        "revenues": 250176000,
        "SalesRevenueNet": 489259000,
        "CostOfGoodsSold": 153409000,
        "GrossProfit": 60878000,
        "OperatingExpenses": 12974397,
        "SellingGeneralAndAdministrativeExpense": 138600000,
        "ResearchAndDevelopmentExpense": 10100000,
        "OperatingIncomeLoss": 45085,
        "InterestExpense": 10193000,
        "IncomeBeforeIncomeTaxes": 0,
        "IncomeTaxExpenseBenefit": 882000,
        "NetIncomeLoss": 270815,
        "PreferredStockDividendsAndOtherAdjustments": 1700500,
        "NetIncomeLossAvailableToCommonStockholdersBasic": 5161585.5,
        "EarningsPerShareBasic": 0.46,
        "EarningsPerShareDiluted": 0.54,
        "WeightedAverageNumberOfSharesOutstandingBasic": 50197000,
        "WeightedAverageNumberOfDilutedSharesOutstanding": 52993874,
        "NetCashProvidedByUsedInOperatingActivities": 8350215,
        "NetCashProvidedByUsedInInvestingActivities": -28169500,
        "NetCashProvidedByUsedInFinancingActivities": 77048.5,
        "CashAndCashEquivalentsPeriodIncreaseDecrease": 229.5,
        "CashAndCashEquivalentsAtCarryingValue": 37168000,
        "PaymentsToAcquirePropertyPlantAndEquipment": 6801000,
        "ProceedsFromIssuanceOfCommonStock": 2377500,
        "PaymentsOfDividends": 23039000,
        "RepaymentsOfDebt": 17000000,
        "ProceedsFromIssuanceOfDebt": 20000000,
        "DepreciationAndAmortization": 14251000,
        "InventoryNet": 38315000,
        "AccountsReceivableNetCurrent": 52027000,
        "AccountsPayableCurrent": 11884000,
        "Goodwill": 160841000,
        "IntangibleAssetsNetExcludingGoodwill": 54800000,
        "PropertyPlantAndEquipmentNet": 44473000,
        "LongTermDebtNoncurrent": 444461000,
        "ShortTermBorrowings": 52000000,
        "IncomeTaxesPayableCurrent": 0,
        "EntityIncorporationStateCountryCode": 0,
        "EntityFilerCategory": 0,
        "AltmanZPrime": 0.15945958838452,
        "AltmanZDoublePrime": 0.48866259072975,
        "OhlsonOScore": -6.0891372623635,
        "OhlsonOScoreProb": 0.0022622349012266,
        "ZmijewskiXScore": -1.2189900126527,
        "SpringateSScore": 0.09444930875576,
        "TafflerZScore": 3.2,
        "FulmerHScore": 8.9790652667555,
        "GroverGScore": 0.17232678615271,
        "BeneishMScore": -4.689290377842299,
        "PiotroskiFScore": 3
    },
    "indicatorNames": {
        "company_id": "company_id_missing",
        "fiscal_year": "fiscal_year_missing",
        "label": "label_missing",
        "current_ratio": "current_ratio_missing",
        "quick_ratio": "quick_ratio_missing",
        "debt_to_assets": "debt_to_assets_missing",
        "debt_to_equity": "debt_to_equity_missing",
        "net_margin": "net_margin_missing",
        "roa": "roa_missing",
        "roe": "roe_missing",
        "asset_turnover": "asset_turnover_missing",
        "inventory_turnover": "inventory_turnover_missing",
        "cash_ratio": "cash_ratio_missing",
        "operating_margin": "operating_margin_missing",
        "revenues_leve": "revenues_leve_missing",
        "OperatingIncomeLoss_leve": "OperatingIncomeLoss_leve_missing",
        "NetIncomeLoss_leve": "NetIncomeLoss_leve_missing",
        "assets_leve": "assets_leve_missing",
        "revenues_tren": "revenues_tren_missing",
        "OperatingIncomeLoss_tren": "OperatingIncomeLoss_tren_missing",
        "NetIncomeLoss_tren": "NetIncomeLoss_tren_missing",
        "assets_tren": "assets_tren_missing",
        "revenues_vola": "revenues_vola_missing",
        "OperatingIncomeLoss_vola": "OperatingIncomeLoss_vola_missing",
        "NetIncomeLoss_vola": "NetIncomeLoss_vola_missing",
        "assets_vola": "assets_vola_missing",
        "ln_assets": "ln_assets_missing",
        "ln_revenues": "ln_revenues_missing",
        "DividendOmission": "DividendOmission_missing",
        "DebtIssuanceSpike": "DebtIssuanceSpike_missing",
        "DebtRepaymentSpike": "DebtRepaymentSpike_missing",
        "TL_TA": "TL_TA_missing",
        "Debt_Assets": "Debt_Assets_missing",
        "EBIT_InterestExpense": "EBIT_InterestExpense_missing",
        "EBITDA_InterestExpense": "EBITDA_InterestExpense_missing",
        "CFO_Liabilities": "CFO_Liabilities_missing",
        "CFO_DebtService": "CFO_DebtService_missing",
        "WC_TA": "WC_TA_missing",
        "CurrentRatio": "CurrentRatio_missing",
        "QuickRatio": "QuickRatio_missing",
        "ROA": "ROA_missing",
        "OperatingMargin": "OperatingMargin_missing",
        "DaysAR": "DaysAR_missing",
        "DaysINV": "DaysINV_missing",
        "DaysAP": "DaysAP_missing",
        "CashConversionCycle": "CashConversionCycle_missing",
        "Accruals": "Accruals_missing",
        "assets": "assets_missing",
        "CurrentAssets": "CurrentAssets_missing",
        "NoncurrentAssets": "NoncurrentAssets_missing",
        "liabilities": "liabilities_missing",
        "CurrentLiabilities": "CurrentLiabilities_missing",
        "NoncurrentLiabilities": "NoncurrentLiabilities_missing",
        "LiabilitiesAndStockholdersEquity": "LiabilitiesAndStockholdersEquity_missing",
        "equity": "equity_missing",
        "CommonStockValue": "CommonStockValue_missing",
        "RetainedEarningsAccumulatedDeficit": "RetainedEarningsAccumulatedDeficit_missing",
        "AccumulatedOtherComprehensiveIncomeLoss": "AccumulatedOtherComprehensiveIncomeLoss_missing",
        "MinorityInterest": "MinorityInterest_missing",
        "revenues": "revenues_missing",
        "SalesRevenueNet": "SalesRevenueNet_missing",
        "CostOfGoodsSold": "CostOfGoodsSold_missing",
        "GrossProfit": "GrossProfit_missing",
        "OperatingExpenses": "OperatingExpenses_missing",
        "SellingGeneralAndAdministrativeExpense": "SellingGeneralAndAdministrativeExpense_missing",
        "ResearchAndDevelopmentExpense": "ResearchAndDevelopmentExpense_missing",
        "OperatingIncomeLoss": "OperatingIncomeLoss_missing",
        "InterestExpense": "InterestExpense_missing",
        "IncomeBeforeIncomeTaxes": "IncomeBeforeIncomeTaxes_missing",
        "IncomeTaxExpenseBenefit": "IncomeTaxExpenseBenefit_missing",
        "NetIncomeLoss": "NetIncomeLoss_missing",
        "PreferredStockDividendsAndOtherAdjustments": "PreferredStockDividendsAndOtherAdjustments_missing",
        "NetIncomeLossAvailableToCommonStockholdersBasic": "NetIncomeLossAvailableToCommonStockholdersBasic_missing",
        "EarningsPerShareBasic": "EarningsPerShareBasic_missing",
        "EarningsPerShareDiluted": "EarningsPerShareDiluted_missing",
        "WeightedAverageNumberOfSharesOutstandingBasic": "WeightedAverageNumberOfSharesOutstandingBasic_missing",
        "WeightedAverageNumberOfDilutedSharesOutstanding": "WeightedAverageNumberOfDilutedSharesOutstanding_missing",
        "NetCashProvidedByUsedInOperatingActivities": "NetCashProvidedByUsedInOperatingActivities_missing",
        "NetCashProvidedByUsedInInvestingActivities": "NetCashProvidedByUsedInInvestingActivities_missing",
        "NetCashProvidedByUsedInFinancingActivities": "NetCashProvidedByUsedInFinancingActivities_missing",
        "CashAndCashEquivalentsPeriodIncreaseDecrease": "CashAndCashEquivalentsPeriodIncreaseDecrease_missing",
        "CashAndCashEquivalentsAtCarryingValue": "CashAndCashEquivalentsAtCarryingValue_missing",
        "PaymentsToAcquirePropertyPlantAndEquipment": "PaymentsToAcquirePropertyPlantAndEquipment_missing",
        "ProceedsFromIssuanceOfCommonStock": "ProceedsFromIssuanceOfCommonStock_missing",
        "PaymentsOfDividends": "PaymentsOfDividends_missing",
        "RepaymentsOfDebt": "RepaymentsOfDebt_missing",
        "ProceedsFromIssuanceOfDebt": "ProceedsFromIssuanceOfDebt_missing",
        "DepreciationAndAmortization": "DepreciationAndAmortization_missing",
        "InventoryNet": "InventoryNet_missing",
        "AccountsReceivableNetCurrent": "AccountsReceivableNetCurrent_missing",
        "AccountsPayableCurrent": "AccountsPayableCurrent_missing",
        "Goodwill": "Goodwill_missing",
        "IntangibleAssetsNetExcludingGoodwill": "IntangibleAssetsNetExcludingGoodwill_missing",
        "PropertyPlantAndEquipmentNet": "PropertyPlantAndEquipmentNet_missing",
        "LongTermDebtNoncurrent": "LongTermDebtNoncurrent_missing",
        "ShortTermBorrowings": "ShortTermBorrowings_missing",
        "IncomeTaxesPayableCurrent": "IncomeTaxesPayableCurrent_missing",
        "EntityIncorporationStateCountryCode": "EntityIncorporationStateCountryCode_missing",
        "EntityFilerCategory": "EntityFilerCategory_missing",
        "AltmanZPrime": "AltmanZPrime_missing",
        "AltmanZDoublePrime": "AltmanZDoublePrime_missing",
        "OhlsonOScore": "OhlsonOScore_missing",
        "OhlsonOScoreProb": "OhlsonOScoreProb_missing",
        "ZmijewskiXScore": "ZmijewskiXScore_missing",
        "SpringateSScore": "SpringateSScore_missing",
        "TafflerZScore": "TafflerZScore_missing",
        "FulmerHScore": "FulmerHScore_missing",
        "GroverGScore": "GroverGScore_missing",
        "BeneishMScore": "BeneishMScore_missing",
        "PiotroskiFScore": "PiotroskiFScore_missing"
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
        "debt_to_equity": 0.8619952323951587,
        "net_margin": 0.033230312621621794,
        "roa": 0.0021612117453119477,
        "roe": 0.02234545616458824,
        "asset_turnover": 0.503707302841848,
        "inventory_turnover": 2.7518948580759592,
        "cash_ratio": 0.3652315692063632,
        "operating_margin": 0.06281943597788078,
        "revenues_leve": 250176000,
        "OperatingIncomeLoss_leve": 45085,
        "NetIncomeLoss_leve": 270815,
        "assets_leve": 683980000,
        "revenues_tren": 0,
        "OperatingIncomeLoss_tren": 0,
        "NetIncomeLoss_tren": 0,
        "assets_tren": -26019641,
        "revenues_vola": 3668535.3881730684,
        "OperatingIncomeLoss_vola": 27952284.613694817,
        "NetIncomeLoss_vola": 37433444.5,
        "assets_vola": 1414201895.1112053,
        "ln_assets": 20.360486585288182,
        "ln_revenues": 19.74753025580043,
        "DividendOmission": 0,
        "DebtIssuanceSpike": 0,
        "DebtRepaymentSpike": 0,
        "TL_TA": 0.50882425975121,
        "Debt_Assets": 0,
        "EBIT_InterestExpense": 1.76223219864155,
        "EBITDA_InterestExpense": 2.27818979416885,
        "CFO_Liabilities": 0,
        "CFO_DebtService": 0.96278511404562,
        "WC_TA": 0.016008192374346,
        "CurrentRatio": 1.63007353396535,
        "QuickRatio": 1.30180978664725,
        "ROA": 0,
        "OperatingMargin": 0.068691335213895,
        "DaysAR": 46.261775587273,
        "DaysINV": 103.98379588132,
        "DaysAP": 57.419165427274,
        "CashConversionCycle": 85.3368190463265,
        "Accruals": -0.02697341846178,
        "assets": 683980000,
        "CurrentAssets": 133894000,
        "NoncurrentAssets": 65680000,
        "liabilities": 226151000,
        "CurrentLiabilities": 52323000,
        "NoncurrentLiabilities": 58800000,
        "LiabilitiesAndStockholdersEquity": 668790000,
        "equity": 131891500,
        "CommonStockValue": 218000,
        "RetainedEarningsAccumulatedDeficit": -839670,
        "AccumulatedOtherComprehensiveIncomeLoss": 0,
        "MinorityInterest": 9505000,
        "revenues": 250176000,
        "SalesRevenueNet": 489259000,
        "CostOfGoodsSold": 153409000,
        "GrossProfit": 60878000,
        "OperatingExpenses": 12974397,
        "SellingGeneralAndAdministrativeExpense": 138600000,
        "ResearchAndDevelopmentExpense": 10100000,
        "OperatingIncomeLoss": 45085,
        "InterestExpense": 10193000,
        "IncomeBeforeIncomeTaxes": 0,
        "IncomeTaxExpenseBenefit": 882000,
        "NetIncomeLoss": 270815,
        "PreferredStockDividendsAndOtherAdjustments": 1700500,
        "NetIncomeLossAvailableToCommonStockholdersBasic": 5161585.5,
        "EarningsPerShareBasic": 0.46,
        "EarningsPerShareDiluted": 0.54,
        "WeightedAverageNumberOfSharesOutstandingBasic": 50197000,
        "WeightedAverageNumberOfDilutedSharesOutstanding": 52993874,
        "NetCashProvidedByUsedInOperatingActivities": 8350215,
        "NetCashProvidedByUsedInInvestingActivities": -28169500,
        "NetCashProvidedByUsedInFinancingActivities": 77048.5,
        "CashAndCashEquivalentsPeriodIncreaseDecrease": 229.5,
        "CashAndCashEquivalentsAtCarryingValue": 37168000,
        "PaymentsToAcquirePropertyPlantAndEquipment": 6801000,
        "ProceedsFromIssuanceOfCommonStock": 2377500,
        "PaymentsOfDividends": 23039000,
        "RepaymentsOfDebt": 17000000,
        "ProceedsFromIssuanceOfDebt": 20000000,
        "DepreciationAndAmortization": 14251000,
        "InventoryNet": 38315000,
        "AccountsReceivableNetCurrent": 52027000,
        "AccountsPayableCurrent": 11884000,
        "Goodwill": 160841000,
        "IntangibleAssetsNetExcludingGoodwill": 54800000,
        "PropertyPlantAndEquipmentNet": 44473000,
        "LongTermDebtNoncurrent": 444461000,
        "ShortTermBorrowings": 52000000,
        "IncomeTaxesPayableCurrent": 0,
        "EntityIncorporationStateCountryCode": 0,
        "EntityFilerCategory": 0,
        "AltmanZPrime": 0.15945958838452,
        "AltmanZDoublePrime": 0.48866259072975,
        "OhlsonOScore": -6.0891372623635,
        "OhlsonOScoreProb": 0.0022622349012266,
        "ZmijewskiXScore": -1.2189900126527,
        "SpringateSScore": 0.09444930875576,
        "TafflerZScore": 3.2,
        "FulmerHScore": 8.9790652667555,
        "GroverGScore": 0.17232678615271,
        "BeneishMScore": -4.689290377842299,
        "PiotroskiFScore": 3,
        "company_id_missing": 0,
        "fiscal_year_missing": 0,
        "label_missing": 0,
        "current_ratio_missing": 0,
        "quick_ratio_missing": 1,
        "debt_to_assets_missing": 0,
        "debt_to_equity_missing": 0,
        "net_margin_missing": 1,
        "roa_missing": 0,
        "roe_missing": 0,
        "asset_turnover_missing": 1,
        "inventory_turnover_missing": 1,
        "cash_ratio_missing": 0,
        "operating_margin_missing": 1,
        "revenues_leve_missing": 1,
        "OperatingIncomeLoss_leve_missing": 0,
        "NetIncomeLoss_leve_missing": 0,
        "assets_leve_missing": 0,
        "revenues_tren_missing": 1,
        "OperatingIncomeLoss_tren_missing": 1,
        "NetIncomeLoss_tren_missing": 1,
        "assets_tren_missing": 1,
        "revenues_vola_missing": 1,
        "OperatingIncomeLoss_vola_missing": 1,
        "NetIncomeLoss_vola_missing": 1,
        "assets_vola_missing": 1,
        "ln_assets_missing": 0,
        "ln_revenues_missing": 1,
        "DividendOmission_missing": 0,
        "DebtIssuanceSpike_missing": 0,
        "DebtRepaymentSpike_missing": 0,
        "TL_TA_missing": 0,
        "Debt_Assets_missing": 0,
        "EBIT_InterestExpense_missing": 0,
        "EBITDA_InterestExpense_missing": 0,
        "CFO_Liabilities_missing": 0,
        "CFO_DebtService_missing": 0,
        "WC_TA_missing": 0,
        "CurrentRatio_missing": 0,
        "QuickRatio_missing": 0,
        "ROA_missing": 0,
        "OperatingMargin_missing": 1,
        "DaysAR_missing": 1,
        "DaysINV_missing": 1,
        "DaysAP_missing": 1,
        "CashConversionCycle_missing": 1,
        "Accruals_missing": 0,
        "assets_missing": 0,
        "CurrentAssets_missing": 0,
        "NoncurrentAssets_missing": 1,
        "liabilities_missing": 0,
        "CurrentLiabilities_missing": 0,
        "NoncurrentLiabilities_missing": 1,
        "LiabilitiesAndStockholdersEquity_missing": 0,
        "equity_missing": 0,
        "CommonStockValue_missing": 0,
        "RetainedEarningsAccumulatedDeficit_missing": 0,
        "AccumulatedOtherComprehensiveIncomeLoss_missing": 1,
        "MinorityInterest_missing": 1,
        "revenues_missing": 1,
        "SalesRevenueNet_missing": 1,
        "CostOfGoodsSold_missing": 1,
        "GrossProfit_missing": 1,
        "OperatingExpenses_missing": 1,
        "SellingGeneralAndAdministrativeExpense_missing": 1,
        "ResearchAndDevelopmentExpense_missing": 1,
        "OperatingIncomeLoss_missing": 0,
        "InterestExpense_missing": 0,
        "IncomeBeforeIncomeTaxes_missing": 1,
        "IncomeTaxExpenseBenefit_missing": 0,
        "NetIncomeLoss_missing": 0,
        "PreferredStockDividendsAndOtherAdjustments_missing": 1,
        "NetIncomeLossAvailableToCommonStockholdersBasic_missing": 1,
        "EarningsPerShareBasic_missing": 0,
        "EarningsPerShareDiluted_missing": 0,
        "WeightedAverageNumberOfSharesOutstandingBasic_missing": 0,
        "WeightedAverageNumberOfDilutedSharesOutstanding_missing": 0,
        "NetCashProvidedByUsedInOperatingActivities_missing": 0,
        "NetCashProvidedByUsedInInvestingActivities_missing": 0,
        "NetCashProvidedByUsedInFinancingActivities_missing": 0,
        "CashAndCashEquivalentsPeriodIncreaseDecrease_missing": 1,
        "CashAndCashEquivalentsAtCarryingValue_missing": 0,
        "PaymentsToAcquirePropertyPlantAndEquipment_missing": 0,
        "ProceedsFromIssuanceOfCommonStock_missing": 1,
        "PaymentsOfDividends_missing": 1,
        "RepaymentsOfDebt_missing": 1,
        "ProceedsFromIssuanceOfDebt_missing": 1,
        "DepreciationAndAmortization_missing": 1,
        "InventoryNet_missing": 1,
        "AccountsReceivableNetCurrent_missing": 0,
        "AccountsPayableCurrent_missing": 0,
        "Goodwill_missing": 0,
        "IntangibleAssetsNetExcludingGoodwill_missing": 1,
        "PropertyPlantAndEquipmentNet_missing": 0,
        "LongTermDebtNoncurrent_missing": 1,
        "ShortTermBorrowings_missing": 1,
        "IncomeTaxesPayableCurrent_missing": 1,
        "EntityIncorporationStateCountryCode_missing": 1,
        "EntityFilerCategory_missing": 1,
        "AltmanZPrime_missing": 0,
        "AltmanZDoublePrime_missing": 0,
        "OhlsonOScore_missing": 0,
        "OhlsonOScoreProb_missing": 0,
        "ZmijewskiXScore_missing": 0,
        "SpringateSScore_missing": 0,
        "TafflerZScore_missing": 0,
        "FulmerHScore_missing": 1,
        "GroverGScore_missing": 0,
        "BeneishMScore_missing": 0,
        "PiotroskiFScore_missing": 0
    },
    "iqr": {
        "company_id": 5100,
        "fiscal_year": 5,
        "label": 1,
        "current_ratio": 1.3827459050619193,
        "quick_ratio": 1,
        "debt_to_assets": 0.42126595758989516,
        "debt_to_equity": 1.7017198353593066,
        "net_margin": 1,
        "roa": 0.08231505469191362,
        "roe": 0.09725372954285413,
        "asset_turnover": 1,
        "inventory_turnover": 1,
        "cash_ratio": 0.5142925778860005,
        "operating_margin": 1,
        "revenues_leve": 1,
        "OperatingIncomeLoss_leve": 66854707,
        "NetIncomeLoss_leve": 75309290,
        "assets_leve": 5368926760,
        "revenues_tren": 1,
        "OperatingIncomeLoss_tren": 1,
        "NetIncomeLoss_tren": 1,
        "assets_tren": 1,
        "revenues_vola": 1,
        "OperatingIncomeLoss_vola": 1,
        "NetIncomeLoss_vola": 1,
        "assets_vola": 1,
        "ln_assets": 4.702217012571836,
        "ln_revenues": 1,
        "DividendOmission": 1,
        "DebtIssuanceSpike": 1,
        "DebtRepaymentSpike": 1,
        "TL_TA": 0.72692321272769,
        "Debt_Assets": 0.12790098709787,
        "EBIT_InterestExpense": 1.8498168498168002,
        "EBITDA_InterestExpense": 2.4096296736590004,
        "CFO_Liabilities": 0.076495897983012,
        "CFO_DebtService": 2.6324784879888,
        "WC_TA": 0.29104315055072,
        "CurrentRatio": 1.394859681057,
        "QuickRatio": 1.11175943768131,
        "ROA": 0.082315054691913,
        "OperatingMargin": 1,
        "DaysAR": 1,
        "DaysINV": 1,
        "DaysAP": 1,
        "CashConversionCycle": 1,
        "Accruals": 0.063368686494104,
        "assets": 5368926760,
        "CurrentAssets": 494247000,
        "NoncurrentAssets": 1,
        "liabilities": 1651200177,
        "CurrentLiabilities": 266426803,
        "NoncurrentLiabilities": 1,
        "LiabilitiesAndStockholdersEquity": 4745072000,
        "equity": 1045344000,
        "CommonStockValue": 1271000,
        "RetainedEarningsAccumulatedDeficit": 345719000,
        "AccumulatedOtherComprehensiveIncomeLoss": 1,
        "MinorityInterest": 1,
        "revenues": 1,
        "SalesRevenueNet": 1,
        "CostOfGoodsSold": 1,
        "GrossProfit": 1,
        "OperatingExpenses": 1,
        "SellingGeneralAndAdministrativeExpense": 1,
        "ResearchAndDevelopmentExpense": 1,
        "OperatingIncomeLoss": 66854707,
        "InterestExpense": 30266262,
        "IncomeBeforeIncomeTaxes": 1,
        "IncomeTaxExpenseBenefit": 22818000,
        "NetIncomeLoss": 75309290,
        "PreferredStockDividendsAndOtherAdjustments": 1,
        "NetIncomeLossAvailableToCommonStockholdersBasic": 1,
        "EarningsPerShareBasic": 1.27,
        "EarningsPerShareDiluted": 1.16,
        "WeightedAverageNumberOfSharesOutstandingBasic": 59200000,
        "WeightedAverageNumberOfDilutedSharesOutstanding": 53502000,
        "NetCashProvidedByUsedInOperatingActivities": 190328880,
        "NetCashProvidedByUsedInInvestingActivities": 197682100,
        "NetCashProvidedByUsedInFinancingActivities": 30808000,
        "CashAndCashEquivalentsPeriodIncreaseDecrease": 1,
        "CashAndCashEquivalentsAtCarryingValue": 172647695,
        "PaymentsToAcquirePropertyPlantAndEquipment": 19476000,
        "ProceedsFromIssuanceOfCommonStock": 1,
        "PaymentsOfDividends": 1,
        "RepaymentsOfDebt": 1,
        "ProceedsFromIssuanceOfDebt": 1,
        "DepreciationAndAmortization": 1,
        "InventoryNet": 1,
        "AccountsReceivableNetCurrent": 3897000,
        "AccountsPayableCurrent": 20477000,
        "Goodwill": 103877000,
        "IntangibleAssetsNetExcludingGoodwill": 1,
        "PropertyPlantAndEquipmentNet": 295328000,
        "LongTermDebtNoncurrent": 1,
        "ShortTermBorrowings": 1,
        "IncomeTaxesPayableCurrent": 1,
        "EntityIncorporationStateCountryCode": 1,
        "EntityFilerCategory": 1,
        "AltmanZPrime": 1.28435834733447,
        "AltmanZDoublePrime": 3.7348517563648,
        "OhlsonOScore": 1.0018984526786996,
        "OhlsonOScoreProb": 0.0024489252887714003,
        "ZmijewskiXScore": 4.001982927486511,
        "SpringateSScore": 0.410629879973313,
        "TafflerZScore": 3.752470977940583,
        "FulmerHScore": 1,
        "GroverGScore": 0.517762328662225,
        "BeneishMScore": 0.5816641633410997,
        "PiotroskiFScore": 2,
        "company_id_missing": 1,
        "fiscal_year_missing": 1,
        "label_missing": 1,
        "current_ratio_missing": 1,
        "quick_ratio_missing": 1,
        "debt_to_assets_missing": 1,
        "debt_to_equity_missing": 1,
        "net_margin_missing": 1,
        "roa_missing": 1,
        "roe_missing": 1,
        "asset_turnover_missing": 1,
        "inventory_turnover_missing": 1,
        "cash_ratio_missing": 1,
        "operating_margin_missing": 1,
        "revenues_leve_missing": 1,
        "OperatingIncomeLoss_leve_missing": 1,
        "NetIncomeLoss_leve_missing": 1,
        "assets_leve_missing": 1,
        "revenues_tren_missing": 1,
        "OperatingIncomeLoss_tren_missing": 1,
        "NetIncomeLoss_tren_missing": 1,
        "assets_tren_missing": 1,
        "revenues_vola_missing": 1,
        "OperatingIncomeLoss_vola_missing": 1,
        "NetIncomeLoss_vola_missing": 1,
        "assets_vola_missing": 1,
        "ln_assets_missing": 1,
        "ln_revenues_missing": 1,
        "DividendOmission_missing": 1,
        "DebtIssuanceSpike_missing": 1,
        "DebtRepaymentSpike_missing": 1,
        "TL_TA_missing": 1,
        "Debt_Assets_missing": 1,
        "EBIT_InterestExpense_missing": 1,
        "EBITDA_InterestExpense_missing": 1,
        "CFO_Liabilities_missing": 1,
        "CFO_DebtService_missing": 1,
        "WC_TA_missing": 1,
        "CurrentRatio_missing": 1,
        "QuickRatio_missing": 1,
        "ROA_missing": 1,
        "OperatingMargin_missing": 1,
        "DaysAR_missing": 1,
        "DaysINV_missing": 1,
        "DaysAP_missing": 1,
        "CashConversionCycle_missing": 1,
        "Accruals_missing": 1,
        "assets_missing": 1,
        "CurrentAssets_missing": 1,
        "NoncurrentAssets_missing": 1,
        "liabilities_missing": 1,
        "CurrentLiabilities_missing": 1,
        "NoncurrentLiabilities_missing": 1,
        "LiabilitiesAndStockholdersEquity_missing": 1,
        "equity_missing": 1,
        "CommonStockValue_missing": 1,
        "RetainedEarningsAccumulatedDeficit_missing": 1,
        "AccumulatedOtherComprehensiveIncomeLoss_missing": 1,
        "MinorityInterest_missing": 1,
        "revenues_missing": 1,
        "SalesRevenueNet_missing": 1,
        "CostOfGoodsSold_missing": 1,
        "GrossProfit_missing": 1,
        "OperatingExpenses_missing": 1,
        "SellingGeneralAndAdministrativeExpense_missing": 1,
        "ResearchAndDevelopmentExpense_missing": 1,
        "OperatingIncomeLoss_missing": 1,
        "InterestExpense_missing": 1,
        "IncomeBeforeIncomeTaxes_missing": 1,
        "IncomeTaxExpenseBenefit_missing": 1,
        "NetIncomeLoss_missing": 1,
        "PreferredStockDividendsAndOtherAdjustments_missing": 1,
        "NetIncomeLossAvailableToCommonStockholdersBasic_missing": 1,
        "EarningsPerShareBasic_missing": 1,
        "EarningsPerShareDiluted_missing": 1,
        "WeightedAverageNumberOfSharesOutstandingBasic_missing": 1,
        "WeightedAverageNumberOfDilutedSharesOutstanding_missing": 1,
        "NetCashProvidedByUsedInOperatingActivities_missing": 1,
        "NetCashProvidedByUsedInInvestingActivities_missing": 1,
        "NetCashProvidedByUsedInFinancingActivities_missing": 1,
        "CashAndCashEquivalentsPeriodIncreaseDecrease_missing": 1,
        "CashAndCashEquivalentsAtCarryingValue_missing": 1,
        "PaymentsToAcquirePropertyPlantAndEquipment_missing": 1,
        "ProceedsFromIssuanceOfCommonStock_missing": 1,
        "PaymentsOfDividends_missing": 1,
        "RepaymentsOfDebt_missing": 1,
        "ProceedsFromIssuanceOfDebt_missing": 1,
        "DepreciationAndAmortization_missing": 1,
        "InventoryNet_missing": 1,
        "AccountsReceivableNetCurrent_missing": 1,
        "AccountsPayableCurrent_missing": 1,
        "Goodwill_missing": 1,
        "IntangibleAssetsNetExcludingGoodwill_missing": 1,
        "PropertyPlantAndEquipmentNet_missing": 1,
        "LongTermDebtNoncurrent_missing": 1,
        "ShortTermBorrowings_missing": 1,
        "IncomeTaxesPayableCurrent_missing": 1,
        "EntityIncorporationStateCountryCode_missing": 1,
        "EntityFilerCategory_missing": 1,
        "AltmanZPrime_missing": 1,
        "AltmanZDoublePrime_missing": 1,
        "OhlsonOScore_missing": 1,
        "OhlsonOScoreProb_missing": 1,
        "ZmijewskiXScore_missing": 1,
        "SpringateSScore_missing": 1,
        "TafflerZScore_missing": 1,
        "FulmerHScore_missing": 1,
        "GroverGScore_missing": 1,
        "BeneishMScore_missing": 1,
        "PiotroskiFScore_missing": 1
    }
}





# One-hot encoder categories

{
    "categorical": [
        "EntityIncorporationStateCountryCode",
        "EntityFilerCategory"
    ],
    "mapping": {
        "EntityIncorporationStateCountryCode": [
            "EntityIncorporationStateCountryCode_0"
        ],
        "EntityFilerCategory": [
            "EntityFilerCategory_0"
        ]
    }
}