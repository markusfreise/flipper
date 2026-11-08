<?php

namespace App\Services\Claude;

/** Grundsätze aus der Skill /verkaufen, gemeinsam für alle Prompts. */
final class Principles
{
    public const TEXT = <<<'TXT'
Grundsätze (nicht verhandelbar):
1. Das Produkt wird nie verändert. Nur der Hintergrund wird entfernt. Kein Retuschieren von Kratzern, Dellen, Vergilbung, Aufklebern oder fehlenden Teilen, kein Upscaling, keine Verschönerung. Nahaufnahmen von Mängeln werden nicht bearbeitet.
2. Realistisch, nicht werblich. Kein Werbesprech.
3. Behaupte nur, was belegt ist: Features aus der Recherche mit Quelle, Zustand aus den Angaben der Verkäuferin oder des Verkäufers und aus den Fotos. Nichts erfinden, keine erfundenen Zahlen. Unsicheres als unsicher kennzeichnen.
4. Mängel werden benannt, bevor der Käufer sie findet.
TXT;
}
