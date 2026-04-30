<footer class="site-footer">
    <div class="footer-content">
        <p>
            &copy; <?php echo date("Y"); ?> STI Library Management System. All Rights Reserved.
        </p>

       <p style="margin-top:8px; font-size:14px; opacity:0.85;">
            This system is a student project developed for academic purposes only.
            It is not an official platform of STI College and is not affiliated with, endorsed by, or connected to STI.
            All trademarks, logos, and brand names are the property of their respective owners and are used solely for educational demonstration.
      </p>
    </div>
</footer>

<!-- ✅ PUT SCRIPT HERE -->
<script>
if ("serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker.register("/service-worker.js")
      .then(reg => console.log("SW registered", reg))
      .catch(err => console.log("SW error", err));
  });
}
</script>

</body>
</html>