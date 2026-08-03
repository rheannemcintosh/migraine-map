# Personas and Job-to-be-Done

This document defines the primary user types for MigraineMap and the jobs they need the product to accomplish. It establishes a shared understanding of who the product serves and the situations in which they use it, to inform design, prioritisation, and development decisions.

Two personas are defined. Both are patients under the care of a neurologist or headache specialist who are required to keep a diary to be assessed for and kept on treatments. They differ in where they are in that journey, and each represents a distinct set of needs the product must serve. Following the personas, a set of jobs-to-be-done describes the specific moments a user reaches for the app.

## Personas

### Persona 1 — The Established Patient

A long-term migraine patient under ongoing specialist care, representing the core user of the product.

**Context.** Has lived with migraine for years and is under specialist care. Has tried many treatments and keeps a mandatory diary as part of assessment. Has moved between providers with different scoring scales (0 to 3 and 0 to 10) and currently scatters their records across phone notes, a work laptop, and paper.

**Goal.** Keep an accurate, consistent record with as little daily effort as possible, so appointments are productive and treatment decisions rest on real data rather than reconstructed memory.

**Constraints.** Can barely look at a screen during an attack. Scoring from memory after the fact is unreliable. Already knows their own condition well, so does not need heavy onboarding or explanation.

**Biggest frustration.** Remembering to log every single day. This, more than anything, is what breaks the record. An entry missed is an entry gone, and days slip by unlogged until an appointment forces a scramble.

**Product needs.** A daily logging flow low-effort enough to survive a bad day, supported by something that helps the habit stick. Minimal onboarding. Confidence that what they log is exactly what reaches the clinician.

### Persona 2 — The Newly-Referred Patient

A patient recently referred to a specialist and asked to begin keeping a diary for the first time. Represents users at the start of their journey, whose needs around guidance and onboarding differ from the established patient.

**Context.** New to specialist care. Has never kept a structured migraine diary. Has no established sense of their own scale and no logging habit yet.

**Goal.** Start keeping an accurate record from day one, so that when their treatment assessment begins there is clean data behind it rather than an inconsistent first few months.

**Constraints.** Does not yet know what a severe migraine means for them on a number scale, so a bare 0 to 10 box is intimidating and easy to fill in inconsistently. No habit formed, so forgetting is even more likely than for Persona 1. May be anxious and looking for reassurance they are doing it right.

**Biggest frustration.** A blank scale with no reference. Being asked to rate pain 0 to 10 with nothing to anchor against means their early entries are guesses, and inconsistent guesses undermine the whole record.

**Product needs.** Anchored examples that make the scale meaningful from the first entry (0 = no pain at all; 10 = needed hospital treatment), a gentle first-run setup, and the same low-effort daily logging flow the established patient needs.

## Jobs-to-be-Done

The specific moments users reach for MigraineMap.

- **Logging an attack.** When a migraine is happening, I want to record it quickly in the moment, and if I did not manage to, I want to capture it at the end of the day, so my record stays complete without it becoming a chore.

- **Scoring meaningfully.** When I need to put a number on my pain, I want a reference for what each number means, so I can score consistently instead of guessing differently each time.

- **Recording a good day.** When I have had no pain at all, I want to mark the day as pain-free quickly, so my record shows the good days as accurately as the bad ones.

- **Fixing a mistake.** When I mis-scored a day or missed one, I want to go back and correct it, so my record stays accurate.

- **Noting medication.** When I have taken a migraine medication, I want to note it against the day, so the record reflects what I took as well as how I felt.

- **Checking my history.** When I want to see how I have been doing, I want to look back over my past entries, so I can see the picture and notice any days I have missed.

- **Preparing for an appointment.** When I have a specialist appointment coming up, I want to get my record out in a readable form, so I can bring accurate data instead of compiling scattered notes the night before.

- **Starting from scratch.** When I am told to start a diary for the first time, I want to be guided into recording correctly, so my record is trustworthy from the very first entry.
