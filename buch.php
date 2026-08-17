<?php

  session_start();
  if (empty($_SESSION['csrf'])) {
      $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  $csrfToken = $_SESSION['csrf'];
 
 //$werte ??= '';
 $csrfToken ??= '';
 $buchId ??= '';
 $werte['titel'] = "";
 $werte['preis'] = "";
 $werte['beschreibung'] = "";
 $werte['autor'] = "";
 $werte['isbn'] = "";
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buecherverwaltung</title>
</head>
<body>
<form action="buch_neu.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token"
value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" name="id"
value="<?= htmlspecialchars((string) $buchId) ?>">
<label for="titel">Titel *</label>
<input type="text" id="titel" name="titel" required maxlength="200"
value="<?= htmlspecialchars($werte['titel']) ?>">
<label for="autor">Autor *</label>
<input type="text" id="autor" name="autor" required maxlength="150"
value="<?= htmlspecialchars($werte['autor']) ?>">
<label for="isbn">ISBN</label>
<input type="text" id="isbn" name="isbn" maxlength="13"
pattern="[0-9]{13}" value="<?= htmlspecialchars($werte['isbn']) ?>">
<label for="preis">Preis in Euro</label>
<input type="number" id="preis" name="preis" step="0.01" min="0"
value="<?= htmlspecialchars($werte['preis']) ?>">
<label for="beschreibung">Beschreibung</label>
<textarea id="beschreibung" name="beschreibung" rows="5"><?=
htmlspecialchars($werte['beschreibung']) ?></textarea>
<label for="cover">Cover (ein Bild)</label>
<input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
<label for="galerie">Weitere Bilder (mehrere möglich)</label>
<input type="file" id="galerie" name="galerie[]" multiple
accept="image/jpeg,image/png,image/webp">
<button type="submit" name="aktion" value="speichern">Speichern</button>
</form>
</body>
</html>
