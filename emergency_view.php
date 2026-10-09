<?php
require_once 'config.php';

$qr = isset($_GET['qr']) ? trim($_GET['qr']) : '';
$error = '';
$card_data = null;
$fields = [];

if (empty($qr)) {
    $error = "Invalid or missing emergency QR token.";
} else {
    $stmt = $conn->prepare("SELECT e.*, u.name, u.phone, u.profile_image, u.blood_group as u_blood, u.allergies as u_allergies, u.emergency_contact as u_emg FROM emergency_cards e JOIN users u ON e.user_id = u.id WHERE e.qr_token = ?");
    $stmt->bind_param("s", $qr);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $error = "Emergency Card not found or has been disabled.";
    } else {
        $card_data = $res->fetch_assoc();
        
        // Check if patient disabled QR access
        if (isset($card_data['is_qr_enabled']) && (int)$card_data['is_qr_enabled'] === 0) {
            $error = "Access to this Emergency Information Card has been temporarily disabled by the patient.";
            $card_data = null;
        } else {
            $fields = json_decode($card_data['public_fields_json'], true);
            if (!is_array($fields)) $fields = [];
        }
    }
}

include 'includes/header.php';
?>

<div style="max-width: 650px; margin: 2rem auto; padding: 0 1rem 3rem;">
    <?php if ($error || !$card_data): ?>
        <div class="glass-panel" style="padding: 2.5rem; text-align: center; border-left: 4px solid #ff4757; border-radius: 20px;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(255, 71, 87, 0.15); color: #ff4757; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1.5rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="margin-top: 0; color: var(--text-primary);">Emergency Card Unavailable</h2>
            <p style="color: var(--text-secondary); font-size: 1rem;"><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php else: ?>
        <?php
            $p_name = $card_data['name'];
            $blood_group = !empty($card_data['blood_group']) ? $card_data['blood_group'] : (!empty($card_data['u_blood']) ? $card_data['u_blood'] : '');
            $primary_phone = !empty($card_data['primary_contact_phone']) ? $card_data['primary_contact_phone'] : (!empty($card_data['u_emg']) ? $card_data['u_emg'] : $card_data['phone']);
            $primary_name = !empty($card_data['primary_contact_name']) ? $card_data['primary_contact_name'] : 'Primary Contact';
            $primary_rel = !empty($card_data['primary_contact_rel']) ? $card_data['primary_contact_rel'] : '';

            $secondary_phone = !empty($card_data['secondary_contact_phone']) ? $card_data['secondary_contact_phone'] : '';
            $secondary_name = !empty($card_data['secondary_contact_name']) ? $card_data['secondary_contact_name'] : '';
            $secondary_rel = !empty($card_data['secondary_contact_rel']) ? $card_data['secondary_contact_rel'] : '';

            // Parse Allergies
            $allergies_list = [];
            $no_allergies_confirmed = (bool)($card_data['no_allergies_confirmed'] ?? 0);
            if (!empty($card_data['allergies_json'])) {
                $decoded = json_decode($card_data['allergies_json'], true);
                if (is_array($decoded)) $allergies_list = $decoded;
            }
            if (empty($allergies_list) && !$no_allergies_confirmed && !empty($card_data['u_allergies'])) {
                if (stristr($card_data['u_allergies'], 'No known allergies') !== false) {
                    $no_allergies_confirmed = true;
                } else {
                    $items = array_map('trim', explode(',', $card_data['u_allergies']));
                    foreach ($items as $it) {
                        if (!empty($it)) $allergies_list[] = ['allergy' => $it, 'severity' => 'Unspecified', 'reaction' => ''];
                    }
                }
            }

            $profile_img_src = get_profile_image_url($card_data);
            if (empty($profile_img_src)) {
                $profile_img_src = 'https://ui-avatars.com/api/?name=' . urlencode($p_name) . '&background=ff4757&color=fff&size=128';
            }
            $last_updated = !empty($card_data['last_reviewed_at']) ? $card_data['last_reviewed_at'] : ($card_data['updated_at'] ?? date('Y-m-d H:i:s'));
        ?>

        <div class="glass-panel" style="padding: 2rem; border-top: 4px solid #ff4757; box-shadow: 0 15px 35px rgba(255, 71, 87, 0.15); border-radius: 20px;">
            <div style="text-align: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1rem;">
                    <img src="<?php echo $profile_img_src; ?>" alt="<?php echo htmlspecialchars($p_name); ?>" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2.5px solid #ff4757;">
                    <div style="text-align: left;">
                        <h2 style="margin: 0; color: var(--text-primary); font-size: 1.5rem;"><?php echo htmlspecialchars($p_name); ?></h2>
                        <span style="display: inline-block; background: rgba(255, 71, 87, 0.2); color: #ff6b81; border: 1px solid #ff4757; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 800; margin-top: 0.3rem;">
                            <i class="fas fa-ambulance"></i> AUTHORIZED EMERGENCY RESPONDER CARD
                        </span>
                    </div>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                    <i class="fas fa-clock"></i> Last Updated: <?php echo date('M d, Y h:i A', strtotime($last_updated)); ?>
                </div>
            </div>

            <!-- Public Fields Section -->
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                
                <!-- Blood Group -->
                <?php if (in_array('blood_group', $fields) || empty($fields)): ?>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1rem 1.25rem; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <span style="font-size: 0.9rem; color: var(--text-secondary); font-weight: bold;"><i class="fas fa-tint" style="color:#ff4757;"></i> Blood Group:</span>
                        <span style="font-size: 1.1rem; color: var(--text-primary); font-weight: 800;">
                            <?php echo !empty($blood_group) ? htmlspecialchars($blood_group) : '<span style="color:#ffa502;">Not specified</span>'; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Emergency Contacts -->
                <?php if (in_array('emergency_contact', $fields) || empty($fields)): ?>
                    <div style="background: rgba(46, 213, 115, 0.08); border: 1px solid rgba(46, 213, 115, 0.3); padding: 1.25rem; border-radius: 14px;">
                        <div style="font-size: 0.75rem; color: #2ed573; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 0.4rem;">PRIMARY EMERGENCY CONTACT</div>
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.8rem;">
                            <span style="font-size: 1.1rem; color: #ffffff; font-weight: 700;">
                                <?php echo htmlspecialchars($primary_name); ?> <?php echo !empty($primary_rel) ? '(' . htmlspecialchars($primary_rel) . ')' : ''; ?>
                            </span>
                            <span style="font-size: 1.1rem; color: #2ed573; font-weight: 800; font-family: monospace;">
                                <?php echo htmlspecialchars($primary_phone); ?>
                            </span>
                        </div>
                        <a href="tel:<?php echo htmlspecialchars($primary_phone); ?>" class="btn btn-primary" style="width: 100%; text-align: center; background: #2ed573; border-color: #2ed573; color: #000000; font-weight: 800; font-size: 1rem; border-radius: 10px; text-decoration: none;">
                            <i class="fas fa-phone"></i> Call Emergency Contact
                        </a>
                    </div>

                    <?php if (!empty($secondary_phone)): ?>
                        <div style="background: rgba(74, 144, 226, 0.08); border: 1px solid rgba(74, 144, 226, 0.3); padding: 1.25rem; border-radius: 14px;">
                            <div style="font-size: 0.75rem; color: #4a90e2; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 0.4rem;">SECONDARY EMERGENCY CONTACT</div>
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.8rem;">
                                <span style="font-size: 1rem; color: #ffffff; font-weight: 700;">
                                    <?php echo htmlspecialchars(!empty($secondary_name) ? $secondary_name : 'Secondary Contact'); ?> <?php echo !empty($secondary_rel) ? '(' . htmlspecialchars($secondary_rel) . ')' : ''; ?>
                                </span>
                                <span style="font-size: 1rem; color: #4a90e2; font-weight: 800; font-family: monospace;">
                                    <?php echo htmlspecialchars($secondary_phone); ?>
                                </span>
                            </div>
                            <a href="tel:<?php echo htmlspecialchars($secondary_phone); ?>" class="btn btn-outline" style="width: 100%; text-align: center; font-size: 0.95rem; border-radius: 10px; text-decoration: none;">
                                <i class="fas fa-phone"></i> Call Secondary Contact
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Allergies -->
                <?php if (in_array('allergies', $fields) || empty($fields)): ?>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px; border-left: 4px solid #ff4757;">
                        <span style="font-size: 0.9rem; color: #ff4757; font-weight: bold; display: block; margin-bottom: 0.6rem;"><i class="fas fa-allergies"></i> Known Allergies & Reactions:</span>
                        <?php if (!empty($allergies_list)): ?>
                            <?php foreach ($allergies_list as $alg): ?>
                                <?php 
                                    $alg_n = is_array($alg) ? ($alg['allergy'] ?? '') : (string)$alg;
                                    $alg_s = is_array($alg) ? ($alg['severity'] ?? 'Unspecified') : 'Unspecified';
                                    $alg_r = is_array($alg) ? ($alg['reaction'] ?? '') : '';
                                    $bg_color = 'rgba(255, 71, 87, 0.15)';
                                    if (stristr($alg_s, 'severe') !== false) $bg_color = 'rgba(255, 71, 87, 0.3)';
                                    elseif (stristr($alg_s, 'moderate') !== false) $bg_color = 'rgba(255, 165, 2, 0.2)';
                                ?>
                                <div style="background: <?php echo $bg_color; ?>; padding: 0.6rem 0.8rem; border-radius: 8px; margin-bottom: 0.4rem; color: #ffffff; font-size: 0.95rem;">
                                    <strong><?php echo htmlspecialchars($alg_n); ?></strong>
                                    <span style="font-size: 0.75rem; background: rgba(0,0,0,0.4); padding: 0.15rem 0.5rem; border-radius: 4px; margin-left: 0.4rem; text-transform: uppercase; font-weight: 800;"><?php echo htmlspecialchars($alg_s); ?></span>
                                    <?php if (!empty($alg_r)): ?> - <span style="font-size: 0.85rem; color: #cbd5e1;"><?php echo htmlspecialchars($alg_r); ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($no_allergies_confirmed): ?>
                            <div style="color: #2ed573; font-weight: bold; font-size: 0.95rem;"><i class="fas fa-check-circle"></i> Confirmed: No known allergies.</div>
                        <?php else: ?>
                            <div style="color: #ffa502; font-weight: bold; font-size: 0.95rem;"><i class="fas fa-exclamation-triangle"></i> Allergy information not provided.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Conditions -->
                <?php if ((in_array('conditions', $fields) || empty($fields)) && !empty($card_data['conditions_text'])): ?>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                        <span style="font-size: 0.9rem; color: #4a90e2; font-weight: bold; display: block; margin-bottom: 0.4rem;"><i class="fas fa-notes-medical"></i> Important Medical Conditions:</span>
                        <div style="font-size: 0.95rem; color: #ffffff; white-space: pre-line;"><?php echo htmlspecialchars($card_data['conditions_text']); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Medications -->
                <?php if ((in_array('medications', $fields) || empty($fields)) && !empty($card_data['medications_text'])): ?>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                        <span style="font-size: 0.9rem; color: #50e3c2; font-weight: bold; display: block; margin-bottom: 0.4rem;"><i class="fas fa-pills"></i> Current Medications:</span>
                        <div style="font-size: 0.95rem; color: #ffffff; white-space: pre-line;"><?php echo htmlspecialchars($card_data['medications_text']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.3rem;"><i class="fas fa-user-check"></i> Patient-provided current medications.</div>
                    </div>
                <?php endif; ?>

            </div>

            <div style="text-align: center; font-size: 0.8rem; color: var(--text-secondary); border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                🔒 MedicalAk Emergency Protocol • Only patient-selected public fields are visible. Private financial & medical history is strictly protected.
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
