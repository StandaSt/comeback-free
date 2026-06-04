<!DOCTYPE html>
<html>
  <head>
    <base target="_top">
  </head>
  <body>
    <form id="uploadForm">
      <input type="file" id="fileInput" name="file">
      <input type="button" value="Nahrát" onclick="uploadFile()">
    </form>
    <script>
      function uploadFile() {
        const fileInput = document.getElementById('fileInput');
        const file = fileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
          google.script.run
            .withSuccessHandler(onSuccess)
            .uploadData(e.target.result);
        };
        
        reader.readAsText(file, 'UTF-8');
      }

      function onSuccess() {
        google.script.host.close();
      }
    </script>
  </body>
</html>
