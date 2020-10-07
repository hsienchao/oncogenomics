xlsxj = require("xlsx-to-json");
  xlsxj({
    input: "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/samples/Sequencing_Tracking_Master_20131206_PatientInfo_DoNotChange.xlsx", 
    output: "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/samples/Sequencing_Tracking_Master_20131206_PatientInfo_DoNotChange.json"
  }, function(err, result) {
    if(err) {
      console.error(err);
    }else {
      console.log(result);
    }
  });
