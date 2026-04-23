</div><!-- .content -->
</div><!-- .main -->
<script>
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});
</script>
</body>
</html>
