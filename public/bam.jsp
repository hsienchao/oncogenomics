<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="shortcut icon" href="//igv.org/web/img/favicon.ico">
    <title>Integrative Genomics Viewer - BAM Example</title>

    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.css"/>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://igv.org/web/release/0.9.3/igv-0.9.3.css">
    <link rel="stylesheet" type="text/css" href="https://igv.org/web/examples/css/bam.css">
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
    <!--script type="text/javascript" src="https://igv.org/web/release/0.9.3/igv-0.9.3.js"></script-->
    <script type="text/javascript" src="https://fr-s-bsg-onc-d.ncifcrf.gov/onco.sandbox2/public/igv_js/igv.js"></script>


</head>

<body>

<div class="jumbotron">

    <div class="container">
        <h2>The IGV view of patient: <label id="patient_id"></label></h2>
        Samples:
    <select id='selBAMs' style="width: 400px">        
    </select>
    </div>

    
    <!-- container -->

</div>
<!-- jumbotron -->

<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div>


<script type="text/javascript">

    var trackNames = {};
    $(document).ready(function () {
        var patient_id='<%= request.getParameter("patient_id") %>';        
        var locus='<%= request.getParameter("locus") %>';
        var bam_list='<%= request.getParameter("bam_list") %>';


        var bams = bam_list.split(",");
        $('#patient_id').text(patient_id);

        for (var i=0; i<bams.length;i++) {
            var bam = bams[i];            
            var trackName = bam.replace(/\.bwa\.final\.bam/i, "").replace(/Sample_/,"");
            trackNames[bam] = trackName;
            $('#selBAMs').append($('<option>', { value : bam }).text(bam));
        }
        
        var url = getCurrentURL();
        var div = $("#igvDiv")[0],
                options = {
                    showNavigation: true,
                    genome: "hg19",
                    locus: locus,
                    tracks: [
                        {
                            url: url,
                            name: trackNames[$("#selBAMs").val()]
                        }

                    ]
                };

        igv.createBrowser(div, options);

        $('#selBAMs').on('change', function() {            
            var url = getCurrentURL();
            igv.browser.loadTrack({url: url, name: trackNames[$("#selBAMs").val()]});            
        });

    });

    function getCurrentURL() {
        return 'http://fr-s-ccr-cbio-d.ncifcrf.gov/cbioportal/igv_js/bams/' + $("#selBAMs").val();
    }

</script>

</body>

</html>
