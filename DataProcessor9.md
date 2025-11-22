# PLAN
Similar to `DataProcessor5.php`, batches of five were built randomly and evaluated by AI, but now the process was performed manually to avoid the need for truncation and now instead processing the full reports and again leverage the possibilities of more powerful models, in this case `gpt-5` with maximum reasoning effort and extensive processing time granted. The model `grok-4` wasn't used again too, because in past runs, the results were pretty similar anyways. For each batch processing, a new chat was opened and the system cache, system memory and browser cache cleared. In total: 200 total rows in both files / 5 rows per batch = 40 were run. The resulting values `AIExpectedLikelihoodOfBankruptcyAnnualReportStrongerModelGPT` and the corresponding explanations `AIExpectedLikelihoodOfBankruptcyAnnualReportStrongerModelGPTExplanation` were mapped back to each company via its `CIK` and written to `financials_subset.csv` and `financials_solvent_subset.csv`.

# BASE SYSTEM PROMPT

    Please use reasoning and think ultrahard about this:
    # SETUP
    You are a financial analyst.

    # TASK
    For each annual report you receive, estimate the expected likelihood that the company will go bankrupt within the next year.
    Score each report on a scale from 0 (very unlikely) to 100 (very likely) and summarize the main drivers behind your assessment in one short sentence.

    # NOTES
    There are exactly 5 reports. Respond with a compact JSON array that has exactly 5 objects in the same order as the reports. Each object must contain two keys:
    - "score": the bankruptcy likelihood on the 0-100 scale (number)
    - "explanation": a concise string (maximum 300 characters) describing the key reasoning.
    Return only valid JSON with no additional commentary.

    # EXAMPLE
    Example of your output:
    [{"score": 42, "explanation": "High leverage but improving cash flow."}]

(Note: The leading "Please use reasoning and think ultrahard about this:" is just a trick to circumvent the processing time limit, which OpenAI normally uses to save costs on their side)

# NOTES

[processing took: 2 minutes and 48 seconds]

[processing took: 3 minutes and 22 seconds]

[processing took: 2 minutes and 42 seconds]

[processing took: 2 minutes and 39 seconds]

[processing took: 2 minutes and 56 seconds]

[processing took: 3 minutes and 17 seconds]

[processing took: 3 minutes and 8 seconds]

[processing took: 4 minutes and 2 seconds]

[processing took: 2 minutes and 29 seconds]

[processing took: 3 minutes and 49 seconds]

[processing took: 2 minutes and 34 seconds]

[processing took: 2 minutes and 57 seconds]

[processing took: 3 minutes and 4 seconds]

[processing took: 3 minutes and 15 seconds]

[processing took: 2 minutes and 35 seconds]

[processing took: 3 minutes and 11 seconds]

[processing took: 4 minutes and 14 seconds]

[processing took: 3 minutes and 27 seconds]

[processing took: 2 minutes and 48 seconds]

[processing took: 2 minutes and 55 seconds]

[processing took: 2 minutes and 46 seconds]

[processing took: 2 minutes and 30 seconds]

[processing took: 3 minutes and 1 second]

[processing took: 2 minutes and 49 seconds]

[processing took: 2 minutes and 2 seconds]

[processing took: 3 minutes and 40 seconds]

[processing took: 2 minutes and 56 seconds]

[processing took: 3 minutes and 5 seconds]

[processing took: 3 minutes and 22 seconds]

[processing took: 2 minutes and 31 seconds]

[processing took: 4 minutes and 4 seconds]

[processing took: 3 minutes and 29 seconds]

[processing took: 2 minutes and 46 seconds]

[processing took: 2 minutes and 38 seconds]

[processing took: 2 minutes and 51 seconds]

[processing took: 3 minutes and 42 seconds]

[processing took: 2 minutes and 33 seconds]

[processing took: 3 minutes and 6 seconds]

[processing took: 2 minutes and 46 seconds]

[processing took: 2 minutes and 56 seconds]





