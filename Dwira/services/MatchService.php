<?php

class MatchService
{
    /* ==============================
       MATCH : BIEN → DEMANDES
    =============================== */
    public static function matchWithDemandes(PDO $pdo, int $bienId): void
    {
        $stmt = $pdo->prepare("SELECT * FROM biens WHERE id = ?");
        $stmt->execute([$bienId]);
        $bien = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bien) {
            return;
        }

        $stmt = $pdo->query("SELECT * FROM clients_demandes");
        $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($demandes as $demande) {
            $score = self::calculerScore($bien, $demande);

            if ($score >= 60) {
                self::insertOrUpdateMatch($pdo, (int)$bien['id'], (int)$demande['id'], $score);
            }
        }
    }

    /* ==============================
       MATCH : DEMANDE → BIENS
    =============================== */
    public static function matchWithBiens(PDO $pdo, int $demandeId): void
    {
        $stmt = $pdo->prepare("SELECT * FROM clients_demandes WHERE id = ?");
        $stmt->execute([$demandeId]);
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$demande) {
            return;
        }

        $stmt = $pdo->query("SELECT * FROM biens");
        $biens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($biens as $bien) {
            $score = self::calculerScore($bien, $demande);

            if ($score >= 60) {
                self::insertOrUpdateMatch($pdo, (int)$bien['id'], (int)$demande['id'], $score);
            }
        }
    }

    /* ==============================
       CALCUL DU SCORE GLOBAL
    =============================== */
    private static function calculerScore(array $bien, array $demande): float
    {
        // 🔴 BLOQUANT : TYPE
        if (
            isset($bien['type'], $demande['type_bien']) &&
            mb_strtolower(trim($bien['type'])) !== mb_strtolower(trim($demande['type_bien']))
        ) {
            return 0.0;
        }

        // 🔴 BLOQUANT : STATUT (Vente / Location)
        if (
            isset($bien['statut'], $demande['statut']) &&
            mb_strtolower(trim($bien['statut'])) !== mb_strtolower(trim($demande['statut']))
        ) {
            return 0.0;
        }

        // Pondérations
        $pond = [
            'chambres'         => 15,
            'budget'           => 45,
            'ville'            => 15,
            'caracteristiques' => 25,
        ];

        $score = 0.0;

        /* =========================
           CHAMBRES (Appart / Villa)
        ========================== */
        $typeBien = mb_strtolower($bien['type'] ?? '');
        $chBien   = (int)($bien['chambres'] ?? 0);
        $chMin    = (int)($demande['chambres_min'] ?? 0);

        if (in_array($typeBien, ['appartement', 'villa'], true) && $chMin > 0) {
            if ($chBien >= $chMin) {
                $score += $pond['chambres'];
            }
        }

        /* =========================
           BUDGET (critère très fort)
        ========================== */
        $prix      = (float)($bien['prix'] ?? 0);
        $budgetMax = (float)($demande['budget_max'] ?? 0);

        if ($prix > 0 && $budgetMax > 0) {

            $depassement = ($prix - $budgetMax) / $budgetMax;

            // Si le bien dépasse le budget de plus de 30% → on bloque totalement
            if ($depassement > 0.30) {
                return 0.0;
            }

            if ($depassement <= 0) {
                // Bien <= budget
                $ecart = ($budgetMax - $prix) / $budgetMax; // % en dessous

                if ($ecart >= 0.20) {
                    // ≥ 20% en dessous → max points
                    $score += $pond['budget'];
                } elseif ($ecart >= 0.10) {
                    $score += $pond['budget'] * 0.9;
                } else {
                    $score += $pond['budget'] * 0.8;
                }

            } else {
                // Bien au-dessus du budget
                if ($depassement <= 0.05) {
                    $score += $pond['budget'] * 0.7;
                } elseif ($depassement <= 0.10) {
                    $score += $pond['budget'] * 0.4;
                } else { // jusque 30%
                    $score += $pond['budget'] * 0.2;
                }
            }

        } else {
            // Budget non renseigné → neutre
            $score += $pond['budget'] * 0.5;
        }

        /* =========================
           VILLE / ZONE
        ========================== */
        $villeBien    = trim($bien['ville']   ?? '');
        $villeDemande = trim($demande['ville'] ?? '');

        if ($villeBien !== '' && $villeDemande !== '') {
            $score += self::scoreVille($villeBien, $villeDemande, $pond['ville']);
        }

        /* =========================
           CARACTÉRISTIQUES
        ========================== */
        $bienCaracs = json_decode($bien['caracteristiques'] ?? '[]', true);
        if (!is_array($bienCaracs)) {
            $bienCaracs = [];
        }

        // Enrichir avec details (mais c'est optionnel pour le score)
        $details = json_decode($bien['details'] ?? '{}', true);
        if (!is_array($details)) {
            $details = [];
        }

        $bienCaracsEnrichis = self::enrichCaracsWithDetails($bienCaracs, $details, $bien['type'] ?? '');

        $demCaracs = json_decode($demande['caracteristiques'] ?? '[]', true);
        if (!is_array($demCaracs)) {
            $demCaracs = [];
        }

        $totalCarac = count($demCaracs);

        // ✅ Si le client n’a coché aucune caractéristique → ça ne change rien
        if ($totalCarac > 0) {
            $matchedCarac = count(array_intersect($bienCaracsEnrichis, $demCaracs));
            $score += $pond['caracteristiques'] * ($matchedCarac / $totalCarac);
        }

        return round($score, 2);
    }

    /* ==============================
       SCORE VILLE / ZONE
    =============================== */
    private static function scoreVille(string $villeBien, string $villeDemande, float $maxPoints): float
    {
        $vb = mb_strtolower(trim($villeBien));
        $vd = mb_strtolower(trim($villeDemande));

        if ($vb === $vd) {
            return $maxPoints;
        }

        // Zone “Kélibia & environs”
        $zoneKelibia = [
            'kelibia',
            'manzel tmim',
            'hammem ghzez',
            'hammem jabli',
            'ezzahra hammem jabli',
            'dar allouche',
            'karkouane',
            'haouria',
            'tamozrat',
            'azmour'
        ];

        $vbInZone = in_array($vb, $zoneKelibia, true);
        $vdInZone = in_array($vd, $zoneKelibia, true);

        if ($vbInZone && $vdInZone) {
            // même zone mais pas même ville → demi-points
            return $maxPoints * 0.5;
        }

        return 0.0;
    }

    /* ==============================
       ENRICHIR LES CARACTÉRISTIQUES
       AVEC LE JSON details
    =============================== */
    private static function enrichCaracsWithDetails(array $baseCaracs, array $details, string $typeBien): array
    {
        $caracs = $baseCaracs;

        $add = function(string $label) use (&$caracs) {
            if ($label !== '' && !in_array($label, $caracs, true)) {
                $caracs[] = $label;
            }
        };

        $type = mb_strtolower($typeBien);

        // Champs communs
        if (!empty($details['type_rue'])) {
            $add($details['type_rue']);
        }
        if (!empty($details['type_papier'])) {
            $add($details['type_papier']);
        }
        if (!empty($details['distance_plage_m'])) {
            $dist = (float)$details['distance_plage_m'];
            if ($dist > 0 && $dist <= 500) {
                $add('Proche de la plage');
            }
            if ($dist > 0 && $dist <= 150) {
                $add('Très proche de la plage');
            }
        }
        if (!empty($details['proche_plage'])) {
            $add('Proche de la plage');
        }

        // APPARTEMENT
        if ($type === 'appartement') {
            if (!empty($details['vue_mer']))           $add('Vue mer');
            if (!empty($details['chauffage_central'])) $add('Chauffage central');
            if (!empty($details['climatisation']))     $add('Climatisation');
            if (!empty($details['balcon']))            $add('Balcon');
            if (!empty($details['terrasse']))          $add('Terrasse');
            if (!empty($details['ascenseur']))         $add('Ascenseur');
            if (!empty($details['gaz_de_ville']))      $add('Gaz de ville');
            if (!empty($details['cuisine_equipee']))   $add('Cuisine équipée');
            if (!empty($details['place_parking']))     $add('Place parking');
            if (!empty($details['syndic']))            $add('Syndic');
            if (!empty($details['meuble']))            $add('Meublé');
            if (!empty($details['independant']))       $add('Appartement indépendant');
        }

        // VILLA
        if ($type === 'villa') {
            if (!empty($details['piscine']))           $add('Piscine');
            if (!empty($details['jardin']))            $add('Jardin');
            if (!empty($details['terrasse']))          $add('Terrasse');
            if (!empty($details['garage']))            $add('Garage');
            if (!empty($details['studio_indep']))      $add('Studio indépendant');
            if (!empty($details['vue_mer']))           $add('Vue mer');
            if (!empty($details['chauffage_central'])) $add('Chauffage central');
            if (!empty($details['climatisation']))     $add('Climatisation');
            if (!empty($details['gaz_de_ville']))      $add('Gaz de ville');
            if (!empty($details['meuble']))            $add('Meublée');
            if (!empty($details['proche_plage']))      $add('Proche de la plage');
        }

        // TERRAIN
        if ($type === 'terrain') {
            if (!empty($details['constructible'])) $add('Terrain constructible');
            if (!empty($details['coin_angle']))    $add('Terrain d\'angle');
            if (!empty($details['type_terrain']))  $add('Terrain ' . $details['type_terrain']);
            if (!empty($details['zone']))          $add('Zone ' . $details['zone']);
        }

        // LOCAL COMMERCIAL
        if ($type === 'local commercial') {
            if (!empty($details['toilette']))      $add('Toilette');
            if (!empty($details['reserve']))       $add('Réserve');
            if (!empty($details['vitrine']))       $add('Vitrine');
            if (!empty($details['coin_angle']))    $add('Local angle');
            if (!empty($details['trois_phases']))  $add('Électricité 3 phases');
            if (!empty($details['gaz_de_ville']))  $add('Gaz de ville');
            if (!empty($details['alarme']))        $add('Alarme');
        }

        return $caracs;
    }

    /* ==============================
       INSERT / UPDATE MATCH
    =============================== */
    private static function insertOrUpdateMatch(PDO $pdo, int $bienId, int $demandeId, float $score): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO matches (bien_id, demande_id, score, vu, statut)
            VALUES (?, ?, ?, 0, 'nouveau')
            ON DUPLICATE KEY UPDATE score = VALUES(score)
        ");

        $stmt->execute([$bienId, $demandeId, $score]);
    }
}