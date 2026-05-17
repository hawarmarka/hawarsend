<footer class="site-footer minimal-footer">
    <div class="footer-inner footer-inner-minimal">
        <div class="footer-copy footer-copy-centered">
            <p>2026 HawarScript. Tüm hakları saklıdır.</p>
        </div>
    </div>
</footer>
</div><!-- .page-wrapper -->

<script src="/assets/js/app.js"></script>
<?php if ($customJs): ?><script><?= $customJs ?></script><?php endif; ?>

<?php
$footerCode = Settings::get('footer_code', '');
echo $footerCode;
?>
</body>
</html>
