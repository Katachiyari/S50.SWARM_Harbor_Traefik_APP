<?php
require 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_trip') {
        $driver_name   = trim($_POST['driver_name'] ?? '');
        $driver_email  = trim($_POST['driver_email'] ?? '');
        $driver_idafpa = trim($_POST['driver_idafpa'] ?? '');
        $from_city     = trim($_POST['from_city'] ?? '');
        $to_city       = trim($_POST['to_city'] ?? '');
        $trip_date     = $_POST['trip_date'] ?? '';
        $trip_time     = $_POST['trip_time'] ?? '';
        $seats_total   = (int)($_POST['seats_total'] ?? 0);

        if ($driver_name && filter_var($driver_email, FILTER_VALIDATE_EMAIL) && $driver_idafpa && $from_city && $to_city && $trip_date && $trip_time && $seats_total > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO trips 
                (driver_name, driver_email, driver_idafpa, from_city, to_city, trip_date, trip_time, seats_total) 
                VALUES 
                (:driver_name, :driver_email, :driver_idafpa, :from_city, :to_city, :trip_date, :trip_time, :seats_total)
            ");
            $stmt->execute([
                ':driver_name'   => $driver_name,
                ':driver_email'  => $driver_email,
                ':driver_idafpa' => $driver_idafpa,
                ':from_city'     => $from_city,
                ':to_city'       => $to_city,
                ':trip_date'     => $trip_date,
                ':trip_time'     => $trip_time,
                ':seats_total'   => $seats_total,
            ]);
            $message = "Trajet ajouté avec succès.";
        } else {
            $message = "Merci de remplir tous les champs du trajet correctement.";
        }
    }

    if ($action === 'reserve') {
        $trip_id          = (int)($_POST['trip_id'] ?? 0);
        $passenger_name   = trim($_POST['passenger_name'] ?? '');
        $passenger_email  = trim($_POST['passenger_email'] ?? '');
        $passenger_idafpa = trim($_POST['passenger_idafpa'] ?? '');
        $seats_requested  = (int)($_POST['seats'] ?? 0);

        if ($trip_id > 0 && $passenger_name && filter_var($passenger_email, FILTER_VALIDATE_EMAIL) && $passenger_idafpa && $seats_requested > 0) {
            $stmt = $pdo->prepare("SELECT * FROM trips WHERE id = :id");
            $stmt->execute([':id' => $trip_id]);
            $trip = $stmt->fetch();

            if ($trip) {
                $available = $trip['seats_total'] - $trip['seats_taken'];
                if ($seats_requested <= $available) {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO reservations (trip_id, passenger_name, passenger_email, passenger_idafpa, seats)
                            VALUES (:trip_id, :passenger_name, :passenger_email, :passenger_idafpa, :seats)
                        ");
                        $stmt->execute([
                            ':trip_id'          => $trip_id,
                            ':passenger_name'   => $passenger_name,
                            ':passenger_email'  => $passenger_email,
                            ':passenger_idafpa' => $passenger_idafpa,
                            ':seats'            => $seats_requested,
                        ]);

                        $stmt = $pdo->prepare("
                            UPDATE trips 
                            SET seats_taken = seats_taken + :seats 
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            ':seats' => $seats_requested,
                            ':id'    => $trip_id,
                        ]);

                        $pdo->commit();
                        $message = "Réservation enregistrée avec succès 🎉";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = "Erreur lors de la réservation : " . htmlspecialchars($e->getMessage());
                    }
                } else {
                    $message = "Pas assez de places disponibles pour ce trajet.";
                }
            } else {
                $message = "Trajet introuvable.";
            }
        } else {
            $message = "Merci de remplir tous les champs de réservation correctement.";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM trips ORDER BY trip_date ASC, trip_time ASC");
$trips = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mini covoiturage AFPA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
            color: #fff;
        }
        .container {
            background: white;
            color: #212529;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
        }
        h1, h2 {
            color: #6f42c1;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(111, 66, 193, 0.25);
        }
        .btn-primary {
            background: #6f42c1;
            border: none;
        }
        .btn-primary:hover {
            background: #54318a;
        }
        .btn-success {
            background: #198754;
            border: none;
        }
        .btn-success:hover {
            background: #146c43;
        }
        .badge-complet {
            background-color: #dc3545;
            font-weight: 600;
            padding: 0.5em 1em;
            border-radius: 50px;
            font-size: 1rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 justify-content-center align-items-center py-5">

<div class="container w-100" style="max-width: 1000px;">

    <h1 class="mb-4 text-center"><i class="bi bi-people-fill"></i> Mini covoiturage AFPA</h1>

    <?php if ($message): ?>
        <div class="alert alert-info text-center" role="alert">
            <i class="bi bi-info-circle-fill"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="row gy-4">

        <div class="col-md-5">
            <div class="card p-4">
                <h2 class="mb-3"><i class="bi bi-truck-front-fill text-primary"></i> Proposer un trajet</h2>
                <form method="post" novalidate>
                    <input type="hidden" name="action" value="add_trip">

                    <div class="mb-3">
                        <label for="driver_name" class="form-label"><i class="bi bi-person-circle"></i> Nom d'affichage</label>
                        <input type="text" class="form-control" id="driver_name" name="driver_name" required placeholder="Votre nom ou pseudo">
                    </div>
                    <div class="mb-3">
                        <label for="driver_email" class="form-label"><i class="bi bi-envelope-fill"></i> Email</label>
                        <input type="email" class="form-control" id="driver_email" name="driver_email" required placeholder="exemple@domaine.com">
                    </div>
                    <div class="mb-3">
                        <label for="driver_idafpa" class="form-label"><i class="bi bi-credit-card-2-front-fill"></i> ID AFPA</label>
                        <input type="text" class="form-control" id="driver_idafpa" name="driver_idafpa" required placeholder="Votre ID AFPA">
                    </div>

                    <div class="mb-3">
                        <label for="from_city" class="form-label"><i class="bi bi-geo-alt-fill"></i> Ville de départ</label>
                        <input type="text" class="form-control" id="from_city" name="from_city" required placeholder="Exemple: Paris">
                    </div>
                    <div class="mb-3">
                        <label for="to_city" class="form-label"><i class="bi bi-geo-fill"></i> Ville d'arrivée</label>
                        <input type="text" class="form-control" id="to_city" name="to_city" required placeholder="Exemple: Lyon">
                    </div>
                    <div class="mb-3">
                        <label for="trip_date" class="form-label"><i class="bi bi-calendar-event-fill"></i> Date</label>
                        <input type="date" class="form-control" id="trip_date" name="trip_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="trip_time" class="form-label"><i class="bi bi-clock-fill"></i> Heure</label>
                        <input type="time" class="form-control" id="trip_time" name="trip_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="seats_total" class="form-label"><i class="bi bi-people-fill"></i> Nombre de places</label>
                        <input type="number" class="form-control" id="seats_total" name="seats_total" min="1" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle-fill"></i> Publier le trajet</button>
                </form>
            </div>
        </div>

        <div class="col-md-7">
            <h2 class="mb-3"><i class="bi bi-list-stars"></i> Trajets disponibles</h2>
            <?php if (empty($trips)): ?>
                <p class="text-center fs-5"><i class="bi bi-emoji-frown-fill"></i> Aucun trajet pour le moment.</p>
            <?php else: ?>
                <?php foreach ($trips as $trip):
                    $available = $trip['seats_total'] - $trip['seats_taken'];
                ?>
                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-arrow-right-circle-fill text-primary"></i>
                                <?= htmlspecialchars($trip['from_city']) ?> → <?= htmlspecialchars($trip['to_city']) ?>
                            </h5>
                            <h6 class="card-subtitle mb-2 text-muted">
                                <i class="bi bi-calendar2-check-fill"></i> Le <?= htmlspecialchars($trip['trip_date']) ?> à <?= htmlspecialchars(substr($trip['trip_time'], 0, 5)) ?>
                            </h6>
                            <p>
                                <i class="bi bi-person-badge-fill"></i> Conducteur : <?= htmlspecialchars($trip['driver_name']) ?> 
                                (<a href="mailto:<?= htmlspecialchars($trip['driver_email']) ?>"><?= htmlspecialchars($trip['driver_email']) ?></a>)
                            </p>
                            <p>
                                <i class="bi bi-car-front-fill"></i> Places : <?= (int)$trip['seats_total'] ?> au total, 
                                <strong><?= (int)$available ?></strong> restantes
                            </p>

                            <?php if ($available > 0): ?>
                                <form method="post" class="row g-3">
                                    <input type="hidden" name="action" value="reserve">
                                    <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">

                                    <div class="col-md-6">
                                        <label for="passenger_name_<?= (int)$trip['id'] ?>" class="form-label">
                                            <i class="bi bi-person-circle"></i> Nom d'affichage
                                        </label>
                                        <input type="text" class="form-control" id="passenger_name_<?= (int)$trip['id'] ?>" name="passenger_name" required placeholder="Votre nom ou pseudo">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="passenger_email_<?= (int)$trip['id'] ?>" class="form-label">
                                            <i class="bi bi-envelope-fill"></i> Email
                                        </label>
                                        <input type="email" class="form-control" id="passenger_email_<?= (int)$trip['id'] ?>" name="passenger_email" required placeholder="exemple@domaine.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="passenger_idafpa_<?= (int)$trip['id'] ?>" class="form-label">
                                            <i class="bi bi-credit-card-2-front-fill"></i> ID AFPA
                                        </label>
                                        <input type="text" class="form-control" id="passenger_idafpa_<?= (int)$trip['id'] ?>" name="passenger_idafpa" required placeholder="Votre ID AFPA">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="seats_<?= (int)$trip['id'] ?>" class="form-label">
                                            <i class="bi bi-people-fill"></i> Places à réserver
                                        </label>
                                        <input type="number" class="form-control" id="seats_<?= (int)$trip['id'] ?>" name="seats" min="1" max="<?= (int)$available ?>" required>
                                    </div>
                                    <div class="col-12 d-grid">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-circle-fill"></i> Réserver
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-complet"><i class="bi bi-x-circle-fill"></i> Complet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
