xlsxj = require("xlsx-to-json");
  xlsxj({
    input: "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/app/storage/data/Sequencing_Tracking_Master.xlsx", 
    output: "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/app/storage/data/Sequencing_Tracking_Master.json"
  }, function(err, result) {
    if(err) {
      console.error(err);
    }else {
      //console.log(result);
    }
  });
