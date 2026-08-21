<?php
/**
 * HTML Template for TCM Treatment & Acupuncture Consent Form PDF
 * 100% Pure PHP with TCPDF
 */
?>
<!-- PAGE 1 -->
<div style="font-family: cid0cs, stsongstdlight, helvetica, sans-serif; color: #000000; font-size: 10pt; line-height: 14pt;">
    
    <!-- Clinic Header -->
    <div style="text-align: center; margin-bottom: 12px;">
        <span style="font-size: 14pt; font-weight: bold; line-height: 19pt;">
            SIAH AH CHEOK CHINESE SIN-SEH CLINIC 谢存灼中医诊所
        </span><br/>
        <span style="font-size: 13pt; font-weight: bold; line-height: 18pt;">
            客户健康问卷（中医）
        </span><br/>
        <span style="font-size: 12pt; font-weight: bold; line-height: 17pt;">
            HEALTH QUESTIONNAIRE (TCM)
        </span>
    </div>

    <!-- Patient Particulars -->
    <table cellpadding="4" cellspacing="0" style="width: 100%; font-size: 9.5pt; border-bottom: 1px solid #000000; margin-bottom: 8px;">
        <tr>
            <td style="width: 38%;"><b>患者姓名 Patient Name :</b> <?= htmlspecialchars($patientName) ?></td>
            <td style="width: 24%;"><b>性别 Sex :</b> <?= htmlspecialchars($patientSex) ?></td>
            <td style="width: 38%;"><b>居民证号码 NRIC No :</b> <?= htmlspecialchars($patientNric) ?></td>
        </tr>
        <tr>
            <td style="width: 38%;"><b>出生日期 Date of Birth :</b> <?= htmlspecialchars($patientDob) ?></td>
            <td colspan="2" style="width: 62%;"><b>联络号码 Contact No :</b> <?= htmlspecialchars($patientContact) ?></td>
        </tr>
    </table>

    <!-- Declarations -->
    <div style="font-weight: bold; font-size: 9.5pt; margin-top: 4px; margin-bottom: 4px;">
        客户声明:<br/>
        Declaration :
    </div>

    <table cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8.8pt; line-height: 12pt;">
        <tr>
            <td style="width: 4%; vertical-align: top; font-weight: bold;">1.</td>
            <td style="width: 96%;">
                本人提供的上述信息是完整和准确的，并无隐瞒可能影响治疗安全性的事实。<br/>
                I acknowledge that the above information given by me is complete and accurate to the best of my knowledge and that no fact that is likely to influence the safety of the treatment(s) that I have signed up for have been withheld.
            </td>
        </tr>
        <tr>
            <td style="width: 4%; vertical-align: top; font-weight: bold;">2.</td>
            <td style="width: 96%;">
                本人征求及同意接受注册中医师所推荐的中医治疗方案，包括但不限于病历记录、针灸、电针治疗、艾灸、温针灸、推拿、拔罐及中药处方等。<br/>
                I hereby request and consent to the performance of Traditional Chinese Medicine treatments as recommended by the registered TCM Practitioner, including but not limited to history-taking, acupuncture, electroacupuncture, indirect moxibustion, warm needle moxibustion, Tuina, cupping, and herbal prescriptions.
            </td>
        </tr>
        <tr>
            <td style="width: 4%; vertical-align: top; font-weight: bold;">3.</td>
            <td style="width: 96%;">
                本人经注册中医师解释并了解以上治疗时或延后有可能出现，出血、瘀血、肿痛、皮肤红晕、小水泡、眩晕、惊厥、烧伤、烫伤、滞针、弯针、断针、气胸等状况。<br/>
                I have been informed and understand the possible risks and complications that may arise during or after the above-said treatment, including but not limited to bleeding, bruising, swelling, pain, skin redness, small blisters, fainting/dizziness, convulsions, burnt, scald, stuck, bent or broken needle, pneumothorax.
            </td>
        </tr>
        <tr>
            <td style="width: 4%; vertical-align: top; font-weight: bold;">4.</td>
            <td style="width: 96%;">
                本人深切了解并不期望注册中医师能预知并解释所有的治疗风险及预后状况。<br/>
                I understand that the registered TCM Practitioner may not be able to anticipate and explain to me each and every possible risk and complication of the above-said treatments.
            </td>
        </tr>
        <tr>
            <td style="width: 4%; vertical-align: top; font-weight: bold;">5.</td>
            <td style="width: 96%;">
                本人应向注册中医师探询所有有关治疗及预后的疑问。<br/>
                I understand that should I have any doubt or query, I may ask the registered TCM Practitioner for more information on the above-said TCM treatments and their possible risks and complications.
            </td>
        </tr>
    </table>

    <!-- Notice -->
    <div style="font-size: 8.8pt; line-height: 11.5pt; margin-top: 6px; margin-bottom: 6px; border-top: 0.5px solid #cccccc; padding-top: 4px;">
        <b>请针对以下问题作答。</b> Please answer the following questions.<br/>
        年龄低于 18 岁或因身体或精神状况无法填写此问卷的客户可由家长, 配偶, 亲属或被授权者代表填写。<br/>
        For any customer under the age of 18 or who are unable to complete this questionnaire due to a physical or mental condition, this questionnaire may be completed by the customer’s parent, spouse, relative or authorized representative.
    </div>

    <!-- Medical History Header -->
    <div style="font-weight: bold; font-size: 9.5pt; margin-bottom: 4px;">
        病史:<br/>
        Medical History:
    </div>

    <!-- Questions 1 & 2 Table -->
    <table cellpadding="4" cellspacing="0" style="width: 100%; font-size: 9pt; line-height: 12pt;">
        <tr>
            <td style="width: 78%;">1. &nbsp; 心脏病 Heart diseases.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('heart_disease', ['heart']) ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">2. &nbsp; 装上心脏起搏器 Implantation of cardiac pacemaker.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('pacemaker') ?></td>
        </tr>
    </table>
</div>

<!-- PAGE BREAK -->
<div style="page-break-before: always;"></div>

<!-- PAGE 2 -->
<div style="font-family: cid0cs, stsongstdlight, helvetica, sans-serif; color: #000000; font-size: 9.2pt; line-height: 12.5pt;">
    
    <!-- Medical Questions 3 to 13 -->
    <table cellpadding="3" cellspacing="0" style="width: 100%; font-size: 9pt; line-height: 12pt;">
        <tr>
            <td style="width: 78%;">3. &nbsp; 糖尿病 Diabetes.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('diabetes') ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">4. &nbsp; 高血压 High blood pressure.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('high_blood_pressure', ['hbp']) ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">5. &nbsp; 高胆固醇 High cholesterol.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('high_cholesterol', ['cholesterol']) ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">
                6. &nbsp; 癌症 Cancer.<br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Specify : <?= $renderSpecify($getSpec('cancer')) ?>
            </td>
            <td style="width: 22%; text-align: right; font-weight: bold; vertical-align: top;"><?= $getAns('cancer') ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">7. &nbsp; 皮肤敏感 Sensitive skin.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('sensitive_skin', ['skin']) ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">
                8. &nbsp; 药物过敏 Allergies.<br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Specify : <?= $renderSpecify($getSpec('allergies')) ?>
            </td>
            <td style="width: 22%; text-align: right; font-weight: bold; vertical-align: top;"><?= $getAns('allergies') ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">9. &nbsp; 艾滋病 HIV/AIDS.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('hiv_aids', ['hiv']) ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">10. 抽搐 Seizures.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('seizures') ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">11. 服用血薄药等抗凝血剂 Consumption of anti-coagulants.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('anti_coagulants', ['anticoagulants']) ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">
                12. 手术 Operation.<br/>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Specify : <?= $renderSpecify($getSpec('operation')) ?>
            </td>
            <td style="width: 22%; text-align: right; font-weight: bold; vertical-align: top;"><?= $getAns('operation') ?></td>
        </tr>
        <tr>
            <td style="width: 78%;">13. 异常出血 Abnormal bleeding.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('abnormal_bleeding', ['bleeding']) ?></td>
        </tr>
    </table>

    <!-- Women Only Section -->
    <div style="margin-top: 5px; margin-bottom: 3px;">
        <b>女性客户。</b><br/>
        <b>For Women Only.</b>
    </div>
    <table cellpadding="3" cellspacing="0" style="width: 100%; font-size: 9pt;">
        <tr>
            <td style="width: 78%;">14. 目前怀孕 Currently pregnant.</td>
            <td style="width: 22%; text-align: right; font-weight: bold;"><?= $getAns('currently_pregnant', ['pregnant']) ?></td>
        </tr>
    </table>

    <!-- Other Conditions Box -->
    <div style="margin-top: 5px; font-size: 8.8pt; line-height: 11.5pt;">
        若有其它医师须知的情况，请在以下注明 If there are other conditions that you wish to inform the physician, please indicate below :
    </div>
    <div style="border: 1px solid #777777; min-height: 28px; padding: 4px 6px; font-size: 8.8pt; color: #000000; margin-top: 2px; margin-bottom: 6px;">
        <?= htmlspecialchars($getSpec('others') ?: 'None / 无') ?>
    </div>

    <!-- Declarations -->
    <div style="font-size: 8pt; line-height: 11pt; border-top: 0.5px solid #cccccc; padding-top: 4px; margin-bottom: 6px;">
        本人确定所提供的信息准确，本人已阅读并理解以上条文，本人谨此同意并授权进行此项治疗。<br/>
        I declare that the above information provided by me are true and to the best of my knowledge. I have carefully read and understood all the above information. I have give my consent to the treatments. I hereby give my permission and consent to the treatment.<br/>
        当患者年龄低于18岁或因身体状况无法签署以上同意书，可由家长、配偶、亲戚或被授权者代表患者签署同意书。<br/>
        This consent form may be signed by patient’s representative (e.g. parents, spouse, relatives or authorized).
    </div>

    <!-- Signatures Table -->
    <table cellpadding="0" cellspacing="0" style="width: 100%; font-size: 8.8pt; margin-top: 4px;">
        <tr>
            <!-- Patient / Representative Signature -->
            <td style="width: 58%; vertical-align: bottom; padding-right: 20px;">
                <div style="height: 42px; text-align: left;">
                    <?php if ($patientSigFile && file_exists($patientSigFile)): ?>
                        <img src="<?= $patientSigFile ?>" height="40" />
                    <?php endif; ?>
                </div>
                <div style="border-bottom: 1px solid #000000; margin-bottom: 2px;"></div>
                <b>患者代表签名 Signature of Patient's Representative.</b>
            </td>
            <!-- Patient Signature Date -->
            <td style="width: 42%; vertical-align: bottom;">
                <div style="height: 42px; text-align: left; line-height: 52px; font-weight: bold;">
                    <?= htmlspecialchars($patientSigDate) ?>
                </div>
                <div style="border-bottom: 1px solid #000000; margin-bottom: 2px;"></div>
                <b>日期 Date.</b>
            </td>
        </tr>
        <tr>
            <!-- Patient Representative Name & Relation -->
            <td colspan="2" style="padding-top: 6px; padding-bottom: 8px;">
                <b>患者代表姓名/与患者关系 Name of Patient's Representative and Relationship to Patient:</b><br/>
                <span style="font-weight: bold;"><?= htmlspecialchars($patientRepText ?: '-') ?></span>
            </td>
        </tr>
        <tr>
            <!-- Physician Signature & Name -->
            <td style="width: 58%; vertical-align: bottom; padding-right: 20px;">
                <div style="height: 42px; text-align: left;">
                    <?php if ($docSigFile && file_exists($docSigFile)): ?>
                        <img src="<?= $docSigFile ?>" height="40" />
                    <?php endif; ?>
                </div>
                <div style="border-bottom: 1px solid #000000; margin-bottom: 2px;"></div>
                <b>当值医师姓名/签名 Name of duty Physician/Signature:</b> &nbsp;
                <span style="font-weight: bold;"><?= htmlspecialchars($physicianName) ?></span>
            </td>
            <!-- Physician Signature Date -->
            <td style="width: 42%; vertical-align: bottom;">
                <div style="height: 42px; text-align: left; line-height: 52px; font-weight: bold;">
                    <?= htmlspecialchars($docSigDate) ?>
                </div>
                <div style="border-bottom: 1px solid #000000; margin-bottom: 2px;"></div>
                <b>日期 Date.</b>
            </td>
        </tr>
    </table>
</div>
