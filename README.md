# Simple Ticker API

Endpoint that accepts: 
1. date range
2. ticker(s)
3. optional grouping weekly flag  
   
Returns JSON payload containing:
1. company name
2. ticker
3. date
4. high price
5. low price
6. closing price
7. grouped by day or week


## Quickstart
Update db configs constants.

    php -S localhost:8080 Ticker\ API.php 

### Example - Group by day:

    curl 'http://localhost:8080?date_from=2017-07-12&date_to=2017-07-13&ticker=WMT,TGT'

    {
        "dayname": {
            "Tuesday": [
                {
                    "name": "Walmart",
                    "ticker": "WMT",
                    "d": "2017-01-03",
                    "high": "69.24",
                    "low": "68.05",
                    "close": "68.66"
                }
            ],
            "Wednesday": [
                {
                    "name": "Walmart",
                    "ticker": "WMT",
                    "d": "2017-01-04",
                    "high": "69.63",
                    "low": "68.60",
                    "close": "69.06"
                }
            ]
        }
    }

### Example - Group weekly:

    curl 'http://localhost:8080?date_from=2017-07-12&date_to=2016-07-13&ticker=WMT,TGT&group_weekly=true'

    {
        "week": {
            "1": [
                {
                    "name": "Walmart",
                    "ticker": "WMT",
                    "d": "2017-01-03",
                    "high": "69.24",
                    "low": "68.05",
                    "close": "68.66"
                },
                {
                    "name": "Walmart",
                    "ticker": "WMT",
                    "d": "2017-01-04",
                    "high": "69.63",
                    "low": "68.60",
                    "close": "69.06"
                }
            ]
        }
    }