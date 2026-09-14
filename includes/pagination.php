
<div class="d-flex justify-content-between align-items-center mt-3">

    <small class="text-muted">
        Showing page <?= $page ?> of <?= $totalPages ?>
        (<?= number_format($totalRecords) ?> records)
    </small>
    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm mb-0">

            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">
                    Previous
                </a>
            </li>

            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);

            for ($i = $start; $i <= $end; $i++):
            ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">
                    Next
                </a>
            </li>

        </ul>
    </nav>
    <?php endif; ?>
</div>
