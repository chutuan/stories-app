<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Đoạn giới thiệu đầu trang thể loại (/genre/{slug}), chỉ in ở trang 1.
 *
 * Trước đây trang thể loại chỉ có tên + một lưới bìa: 16 trang gần như giống hệt nhau
 * về chữ, khác mỗi danh sách truyện. Với Google và người soát AdSense đó là trang mỏng.
 * Mỗi đoạn ở đây viết riêng cho thể loại đó — đừng sinh bằng khuôn mẫu "X stories are
 * about X", vì chính kiểu đó mới là nội dung giá trị thấp.
 *
 * Chỉ nói điều ĐÚNG với kho hiện tại (không hứa số chương, không hứa "có audio"...).
 * Khoá là slug thể loại; thể loại chưa có đoạn nào thì trang chỉ không có đoạn giới thiệu.
 */
final class GenreIntros
{
    private const INTROS = [
        'billionaire' => 'Billionaire stories here are rarely about the money itself. They are about what money hides and what it reveals: the heir who chooses to live quietly, the spouse who finds out the fortune was never what it seemed, the family that measures love in assets. Most of these serials turn on a moment when someone\'s real position comes out in front of the people who looked down on them, and the better ones spend just as long on what happens after.',
        'ceo' => 'CEO stories put power and private life in the same room. A boardroom fight runs alongside a marriage that is coming apart; a founder has to choose between protecting the company and protecting the person who built it with them. Expect office politics, contracts signed under pressure, assistants who know more than anyone thinks, and confrontations in glass-walled meeting rooms.',
        'crime' => 'Crime stories here start after the damage is done: a theft inside a family business, a fraud that empties someone\'s savings, a death everyone agreed to call an accident. The interest is less in the crime than in the person who refuses to let it stand, usually someone the guilty party underestimated. Some are told from the victim\'s side, some from the investigator\'s, a few from inside the scheme.',
        'drama' => 'Drama is the widest shelf in the library: stories about ordinary people pushed to the edge by betrayal, grief, money or pride. A wedding goes wrong in front of everyone, an inheritance splits a family, a quiet employee is blamed for someone else\'s mistake. What ties them together is the turning point where a character stops absorbing it and answers back. If you are new here, this is a good place to start.',
        'family' => 'Family stories are about the people you cannot simply walk away from: parents who play favourites, siblings competing for the same approval, grandparents holding everyone together, and children caught in between. Some are warm, most are complicated, and many end with a conversation at the dinner table that has been years in the making.',
        'family-drama' => 'Family drama is one of the largest shelves in the library. These serials deal with the conflicts that happen behind a closed front door: custody fights, contested wills, in-laws who never accepted the marriage, a parent\'s secret that surfaces at the worst possible moment. They are written to be read in one sitting and to land on an ending rather than a cliffhanger that leads nowhere.',
        'healing' => 'Healing stories are the quieter side of the library. They follow people rebuilding after something has broken: a divorce, a lost job, an estrangement, years spent looking after someone else. The conflict is real, but the focus is on recovery: small kindnesses, a second look at old relationships, and characters who learn to put themselves first without becoming cruel.',
        'mystery' => 'Mystery stories here are built around a question the main character cannot let go of: who sent the letter, where the money went, why a husband\'s phone keeps lighting up at two in the morning. Most are domestic mysteries rather than police procedurals, and the answer is usually closer to home than anyone expected. Each serial is built to answer its question by the final chapter.',
        'rags-to-riches' => 'Rags-to-riches stories follow characters who start with nothing, or lose everything, and climb back. The appeal is in the climb itself: the first job nobody else wanted, the skill no one noticed, the one person who gave them a chance. Some end in wealth and some in something better. Expect underdogs, long odds, and at least one moment where someone who doubted them has to watch.',
        'revenge' => 'Revenge stories follow someone who was wronged — lied about, cheated on, pushed out of a company or a family — and who decides to answer. The satisfaction is in the planning as much as the payoff: evidence gathered quietly, allies chosen carefully, and a confrontation timed for the moment it lands hardest. The best of them also ask what the revenge costs the person taking it.',
        'romance' => 'Romance stories here lean toward second looks and slow trust rather than love at first sight: two people who misjudged each other, a marriage of convenience that turns into something else, an old flame who returns at an inconvenient time. The focus is on conversations and choices rather than explicit scenes.',
        'second-chance' => 'Second-chance stories are about what people do when a door reopens: a reconciliation years after a bitter divorce, a parent trying to earn back an adult child\'s trust, a career rebuilt after a public failure. Not every second chance is taken, and some of these stories are about deciding not to go back. They suit readers who like an ending with some hope in it.',
        'secret-identity' => 'Secret identity stories turn on the gap between who a character appears to be and who they really are: the son-in-law treated as a freeloader who quietly owns the company, the cleaner who once ran a hospital ward, the neighbour with a past nobody asked about. The people around them dig themselves deeper chapter by chapter, and the reveal is the point the whole serial builds toward.',
        'slice-of-life' => 'Slice-of-life stories take ordinary days seriously: a neighbourhood, a small shop, a family routine that shifts when someone new arrives. The stakes are smaller than elsewhere in the library, but they are the stakes most of us actually live with. Read one when you want something gentle that still has a shape and an ending.',
        'suspense' => 'Suspense stories keep you waiting for something you can see coming: a lie about to be exposed, a stranger who knows too much, a deadline that keeps getting closer. They are built to make you open the next chapter, but each one is written toward an ending, not to set up a sequel.',
        'thriller' => 'Thrillers raise the stakes beyond family arguments: stalkers, blackmail, disappearances and people who will go a long way to keep a secret buried. The pace is quicker and the chapters end on harder turns. Some deal with violence and threats, so they are not the gentlest reading on the site.',
    ];

    public static function for(string $slug): ?string
    {
        return self::INTROS[$slug] ?? null;
    }
}
