{{ HTML::style("css/JK-db.css"                 ) }}
{{ HTML::style("css/gsea.css"                  ) }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/libs/jquery/jquery.js') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
  

  <script>
    $(document).ready(
            function() {
                setInterval(function() {
                    var randomnumber = Math.floor(Math.random() * 100);
                    $('#show').text(
                            'I am getting refreshed every 3 seconds..! Random Number ==> '
                                    + randomnumber);
                }, 3000);
            });
    $(function(){
       $('#gsealist').load('{{url('/data/gsea/gene_sets/Khanlab.genesets.html')}}');
    });
  </script>
      
  <div class="easyui-panel" style="width:100%; padding:5px; " id="gsearun" >
    {{Form::open(array('url' => "/viewGSEA/".$sid, 'method' => 'post', 'id' => 'gsea', 'files'=>true))}} 
      <div style="vertical-align: middle; display: inline-block;">
        <b>Data</b>: Sample<select NAME="smplid">{{$smpls}}</select>  
        or Upload a pre-ranked file {{Form::file('prerank_file', ['style'=>'display:inline-block; opacity:0.5;'])}} 
        <b>Genes     </b>: <select NAME='geneset'><option value='Y' checked>Use all data           </option><option value='N' checked>Use current data</option></select>
        Duplicates: <select NAME=geneduprm><option value='Y'        >Keep highest value(abs)</option><option value='N'        >keep all        </option></select> 
        <input type="submit" name="show_gsea"  value="Run GSEA" onsubmit="sendform(this);return false;" style="background: #ACF; border:1px solid #59D"><br>
      </div>
     
      <div style="vertical-align: middle; "><b>Geneset</b>: Upload user's genesets {{Form::file('gsea_file',  ['style'=>'display:inline-block; opacity:0.5;'])}} or use the following genesets:</div>
      <div id="gsealist" style="min-height: 150px"> </div>
    {{Form::close()}}         
  </div>
  <div class="easyui-panel" style="width:100%;height:70%" id="gsealist" >
    <div class="easyui-layout" data-options="fit:true" >
      <div id="runlist" data-options="region:'west',split:true, tools:'#p-tools'" style="width:400px; padding:5px" title="Run list">
        {{$gsealist}}
      </div>
      <div data-options="region:'center'" style="padding:10px" title="Result">
        <iframe id="gseaiframe" name="gseaiframe" style="width:100%;height:100%;" frameBorder="0"> </iframe>
      </div>
    </div>
    <div id="p-tools">
      <a href="javascript:void(0)" class="icon-mini-refresh" onclick="javascript:history.go(0)"></a>
    </div>
  </div>
