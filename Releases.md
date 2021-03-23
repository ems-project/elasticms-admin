## Release 1.15.0
- ClientHelperBundle 
    - [3.6.0](https://github.com/ems-project/EMSClientHelperBundle/releases/tag/3.6.0)
- CommonBundle 
    - [1.8.63](https://github.com/ems-project/EMSCommonBundle/releases/tag/1.8.63)
    - [1.8.62](https://github.com/ems-project/EMSCommonBundle/releases/tag/1.8.62)
    - [1.8.61](https://github.com/ems-project/EMSCommonBundle/releases/tag/1.8.61)

## Release 1.14.50
- No breaking changes
- Documented that version 1.14.3 has deprecated a lot of environment variables
- New documentation : how to upgrade composer dependencies with our docker php-dev image
- Update to Symfony 4.4.20  
- [X] Common Bundle 1.8.60
    - Fixes:
        - Elastica counter for query with more that 10.000 results
- [X] Core Bundle 1.14.50
    - Features:
        - add command revision time-machine (#607)
        - add ES query option in recompute command (#610)
        - refactored form submission view (#594)
    - Fixes:
        - move interact logic into initialize to correct quiet option bypass (#611)
        - composer install with no reqs (#607)
        - rawDataTransformer isset call (#613)
        - post processing catch fatals (#614)
    - Documentation:
        - add contentType documentation (#612)
    