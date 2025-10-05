<?php
require __DIR__ . '/includes/bootstrap.php';

if (empty($_SESSION['survey_completed'])) {
    redirect('index.php');
}

unset($_SESSION['survey_completed']);
include __DIR__ . '/templates/header.php';
?>
<main class="container answer-container">
    <section class="answer-card">
        <h1>Katildiginiz icin tesekkurler.</h1>
        <p>Cevaplariniz basariyla kaydedildi.</p>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
