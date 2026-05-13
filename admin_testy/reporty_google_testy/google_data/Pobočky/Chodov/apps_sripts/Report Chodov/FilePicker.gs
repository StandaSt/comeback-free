<!DOCTYPE html>
<html>
  <body>
    <div style="text-align: center;">
      <h3>Select a file to import</h3>
      <input type="file" id="fileUpload" />
      <button onclick="uploadFile()">Upload</button>
      <div id="status"></div>
      <div id="loadingContent" style="display:none; margin-top: 20px;">
        <p>Uploading data...</p>
      </div>
    </div>
    <script>
      function uploadFile() {
        var file = document.getElementById("fileUpload").files[0];
        if (file) {
          var reader = new FileReader();
          reader.onload = function(e) {
            document.getElementById("status").innerText = "Uploading...";
            document.getElementById("loadingContent").style.display = "block";
            
            google.script.run.withSuccessHandler(onSuccess)
                             .withFailureHandler(onFailure)
                             .importCSVRestia(e.target.result); // Upraveno na importCSVRestia
          };
          reader.readAsText(file);
        } else {
          document.getElementById("status").innerText = "No file selected";
        }
      }

      function onSuccess() {
        document.getElementById("status").innerText = "Upload successful";
        google.script.host.close(); // Zavřít dialog po úspěšném nahrání
      }

      function onFailure(error) {
        document.getElementById("status").innerText = "Upload failed: " + error.message;
      }

      window.onunload = function() {
        google.script.run.protectSheetRestia(); // Ochrana listu po zavření dialogu
      }
    </script>
  </body>
</html>
