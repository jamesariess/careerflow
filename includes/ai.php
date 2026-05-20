<?php
// ============================================================
// CareerFlow – AI Helper (OpenRouter · free models only)
// ============================================================
// OpenRouter gives access to 50+ free models via a single
// OpenAI-compatible endpoint. Users get a free API key at
// https://openrouter.ai  — no credit card required.
//
// Supported free models (as of 2025):
//   mistralai/mistral-7b-instruct:free
//   meta-llama/llama-3.1-8b-instruct:free
//   google/gemma-2-9b-it:free
//   microsoft/phi-3-mini-128k-instruct:free
//   openchat/openchat-7b:free
// ============================================================

require_once __DIR__ . '/db.php';

class AI {
    private static string $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    // ── Core: send a prompt, return the text response ──────────────
    public static function ask(
        string $prompt,
        int    $userId,
        string $feature  = 'general',
        string $systemPrompt = '',
        int    $maxTokens = 1200
    ): string {
        // Load user's key + model
        $row = DB::one('SELECT ai_api_key, ai_model FROM users WHERE id=?', [$userId]);
        $apiKey = trim($row['ai_api_key'] ?? '');
        $model  = trim($row['ai_model']  ?? '') ?: 'mistralai/mistral-7b-instruct:free';

        if (!$apiKey) {
            throw new RuntimeException('No AI API key configured. Please add your OpenRouter key in Settings → AI & Integrations.');
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.7,
        ]);

        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: ' . APP_URL,
                'X-Title: CareerFlow',
            ],
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new RuntimeException("cURL error: $err");

        $data = json_decode($raw, true);

        if ($code !== 200 || empty($data['choices'][0]['message']['content'])) {
            $errMsg = $data['error']['message'] ?? "HTTP $code – $raw";
            throw new RuntimeException("AI API error: $errMsg");
        }

        $text   = trim($data['choices'][0]['message']['content']);
        $tokens = $data['usage']['total_tokens'] ?? 0;

        // Log the interaction
        try {
            DB::run('INSERT INTO ai_logs (user_id,feature,prompt,response,model,tokens_used) VALUES (?,?,?,?,?,?)',
                [$userId, $feature, mb_substr($prompt, 0, 2000), mb_substr($text, 0, 4000), $model, $tokens]);
        } catch (Exception $e) { /* non-fatal */ }

        return $text;
    }

    // ── Analyse an email: is it job-related? category? sentiment? ──
    public static function analyzeEmail(
        string $subject,
        string $body,
        int    $userId,
        array  $applications = []
    ): array {
        $appList = '';
        if (!empty($applications)) {
            $lines = array_map(fn($a) => "- {$a['company']}: {$a['job_title']}", array_slice($applications, 0, 20));
            $appList = "\n\nUser's active applications:\n" . implode("\n", $lines);
        }

        $prompt = <<<PROMPT
Analyze the following email received by a job seeker. Return ONLY a valid JSON object (no markdown, no explanation).

Email Subject: {$subject}
Email Body:
{$body}
{$appList}

Return this exact JSON structure:
{
  "is_job_related": true or false,
  "category": one of ["interview_invite","offer","rejection","screening","assessment","follow_up","application_confirm","recruiter_outreach","other"],
  "sentiment": one of ["positive","neutral","negative"],
  "matched_company": "company name if matched to an application or null",
  "matched_job_title": "job title if matched or null",
  "summary": "1–2 sentence plain-English summary",
  "suggested_status": one of ["Applied","Screening","Assessment","Interview","Final Interview","Offer","Rejected"] or null,
  "requires_reply": true or false,
  "reply_urgency": one of ["high","medium","low"]
}
PROMPT;

        try {
            $raw  = self::ask($prompt, $userId, 'email_analysis', '', 600);
            // Strip any markdown fences
            $json = preg_replace('/```(?:json)?|```/', '', $raw);
            $data = json_decode(trim($json), true);
            if (!is_array($data)) throw new RuntimeException('Invalid JSON from AI');
            return $data;
        } catch (Exception $e) {
            return [
                'is_job_related'  => false,
                'category'        => 'other',
                'sentiment'       => 'neutral',
                'matched_company' => null,
                'matched_job_title' => null,
                'summary'         => 'AI analysis unavailable: ' . $e->getMessage(),
                'suggested_status' => null,
                'requires_reply'  => false,
                'reply_urgency'   => 'low',
            ];
        }
    }

    // ── Generate a smart email reply ───────────────────────────────
    public static function generateReply(
        string $emailSubject,
        string $emailBody,
        string $category,
        array  $userProfile,
        ?array $application = null
    ): string {
        $appCtx = '';
        if ($application) {
            $appCtx = "\nJob applied for: {$application['job_title']} at {$application['company']}";
            if ($application['applied_date']) $appCtx .= " (applied {$application['applied_date']})";
        }

        $toneMap = [
            'interview_invite' => 'enthusiastic and professional, confirming availability',
            'offer'            => 'grateful, professional, and eager',
            'rejection'        => 'gracious, professional, and leaving the door open',
            'screening'        => 'professional and concise',
            'assessment'       => 'professional, asking any clarifying questions if needed',
            'recruiter_outreach' => 'interested but professional',
            'follow_up'        => 'polite and professional',
            'other'            => 'professional and helpful',
        ];
        $tone = $toneMap[$category] ?? 'professional';

        $prompt = <<<PROMPT
Write a professional email reply on behalf of a job seeker.

Tone: {$tone}
Sender's name: {$userProfile['name']}
{$appCtx}

Original email subject: {$emailSubject}
Original email body:
{$emailBody}

Write ONLY the reply email body (no subject line, no "Here is the reply:" preamble).
Start directly with the greeting. Keep it concise (150–250 words max).
End with a professional sign-off using the sender's name.
PROMPT;

        $uid = $userProfile['id'];
        return self::ask($prompt, $uid, 'email_reply', '', 500);
    }

    // ── Generate a cover letter ─────────────────────────────────────
    public static function generateCoverLetter(
        array  $userProfile,
        string $company,
        string $jobTitle,
        string $jobDescription,
        string $tone = 'professional',
        string $extraNotes = ''
    ): string {
        $skills    = $userProfile['skills_summary'] ?? 'not specified';
        $yearsExp  = $userProfile['years_exp'] ?? 0;
        $linkedin  = $userProfile['linkedin_url'] ? "\nLinkedIn: {$userProfile['linkedin_url']}" : '';
        $extra     = $extraNotes ? "\nExtra context from applicant: $extraNotes" : '';

        $tones = [
            'professional'  => 'formal and professional',
            'conversational' => 'warm and conversational yet professional',
            'bold'          => 'confident, bold, and impact-driven',
            'creative'      => 'creative, memorable, and personality-driven',
        ];
        $toneDesc = $tones[$tone] ?? 'professional';

        $prompt = <<<PROMPT
Write a compelling, tailored cover letter for a job application.

Applicant details:
- Name: {$userProfile['name']}
- Years of experience: {$yearsExp}
- Skills & background: {$skills}
{$extra}

Job details:
- Company: {$company}
- Position: {$jobTitle}
- Job Description: {$jobDescription}

Tone: {$toneDesc}

Instructions:
- Address it "Dear Hiring Manager," unless a recruiter name is known
- Open with a strong hook — avoid clichés like "I am writing to apply…"
- Connect the applicant's specific skills to the role's key requirements
- Show genuine interest in the company
- Include a clear call to action in the closing paragraph
- Keep it to 3–4 paragraphs, ~300–380 words
- End with "Sincerely," followed by the applicant's name
- Write ONLY the cover letter body — no subject line, no preamble
PROMPT;

        return self::ask($prompt, (int)$userProfile['id'], 'cover_letter', '', 900);
    }

    // ── Validate an API key by making a minimal test call ──────────
    public static function testKey(string $apiKey, string $model): array {
        $payload = json_encode([
            'model'      => $model,
            'messages'   => [['role'=>'user','content'=>'Reply with only the word: OK']],
            'max_tokens' => 5,
        ]);
        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: ' . APP_URL,
                'X-Title: CareerFlow',
            ],
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($raw, true);
        if ($code === 200 && !empty($data['choices'])) {
            return ['ok' => true, 'message' => 'API key is valid ✓'];
        }
        $errMsg = $data['error']['message'] ?? "HTTP $code";
        return ['ok' => false, 'message' => $errMsg];
    }
}
