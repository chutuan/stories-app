<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class StorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');

        foreach ($this->stories() as $s) {
            $slug = $s['slug'];

            $story = Story::create([
                'title' => $s['title'],
                'slug' => $slug,
                'author' => $s['author'],
                'description' => $s['description'],
                'thumbnail' => $this->seedCover($slug),
                'status' => $s['status'],
                'free_chapters' => $s['free'],
                'views' => random_int(1000, 500000),
                'is_featured' => $s['featured'],
            ]);

            $catIds = collect($s['cats'])
                ->map(fn ($name) => $categories[$name] ?? null)
                ->filter()
                ->all();
            $story->categories()->sync($catIds);

            foreach ($s['chapters'] as $i => $chapter) {
                $n = $i + 1;

                $story->chapters()->create([
                    'number' => $n,
                    'title' => "Chapter {$n}: ".$chapter['title'],
                    'content' => $chapter['content'],
                ]);
            }

            // Vài truyện đọc miễn phí TOÀN BỘ -> phục vụ tab "Miễn phí" (?free=1).
            if (! empty($s['all_free'])) {
                $story->free_chapters = count($s['chapters']);
                $story->save();
            }
        }
    }

    /**
     * Copy ảnh bìa mẫu vào storage/app/public/stories/ và trả về đường dẫn lưu DB.
     * Ảnh nguồn sinh bằng database/seeders/covers/generate-covers.php.
     * Trả null nếu thiếu file -> app tự hiện placeholder.
     */
    protected function seedCover(string $slug): ?string
    {
        $source = __DIR__.'/covers/'.$slug.'.jpg';

        if (! is_file($source)) {
            return null;
        }

        $target = 'stories/'.$slug.'.jpg';
        Storage::disk('public')->put($target, file_get_contents($source));

        return $target;
    }

    /**
     * Dữ liệu mẫu tiếng Anh, mô-típ "giả nghèo hoá ra siêu giàu".
     *
     * @return array<int, array<string, mixed>>
     */
    protected function stories(): array
    {
        return [
            [
                'title' => 'The Janitor Owns the Company',
                'slug' => 'the-janitor-owns-the-company',
                'author' => 'Marcus Vale',
                'status' => 'ongoing',
                'featured' => true,
                'free' => 1,
                'cats' => ['Secret Identity', 'CEO'],
                'description' => 'Every night, Ethan Cole mops the marble floors of Vale Tower while the executives step over his bucket without looking down. What none of them know is that the building, the company, and every share they are fighting over already belong to him. He is not cleaning floors for a paycheck - he is deciding which of them deserves to keep a job.',
                'chapters' => [
                    [
                        'title' => 'The Man With the Mop',
                        'content' => <<<'TXT'
The night shift at Vale Tower began at ten, and by ten fifteen Ethan Cole had already been called invisible twice.

"Move the bucket, old man." A junior analyst kicked it as he passed, and gray water spread across the lobby marble like a slow bruise. He did not apologize. He did not even slow down.

Ethan straightened up. He was thirty-four, not old, but the gray coveralls and the cap pulled low did their work. People saw the uniform and stopped seeing the face. That was the entire point.

"Sorry about him." The security guard, Ruiz, handed over a fresh roll of paper towels. "They're all like that this week. Big vote on Friday. Everyone thinks they're about to be somebody."

"Friday," Ethan repeated, and wrung the mop out slowly. "That soon."

He finished the lobby at midnight, took the service elevator to the basement, and hung the coveralls in locker 12. Then he opened his phone and typed one line to a number saved only as M: "Keep me on the schedule through Friday. I want to see who they are when they think nobody is watching."
TXT
                    ],
                    [
                        'title' => 'Coffee on the Marble Floor',
                        'content' => <<<'TXT'
On Tuesday, a vice president named Grant Whitaker threw a cup of coffee at Ethan's feet on purpose.

"You missed a spot." Whitaker smiled the way men smile when they have an audience. Three interns laughed on cue. "There. Now you have something to do with your night."

Ethan looked at the brown splash across the floor he had polished an hour earlier. He crouched, took out a cloth, and cleaned it without a word. From that angle he could see the interns' shoes shifting, uncomfortable. One of them - a young woman with a cracked phone screen - stepped forward and reached for a second cloth.

"Don't," Whitaker said. "You clean it, you become it."

She cleaned it anyway. "Sorry," she whispered to Ethan. "He's not usually- no. He's always like this. Sorry."

"What's your name?" Ethan asked.

"Priya. Priya Nair. Analyst, first year, and probably unemployed by morning."

Ethan stood, folded the cloth, and looked at Whitaker's back as the man walked toward the elevators. "I doubt that very much," he said.
TXT
                    ],
                    [
                        'title' => 'The Elevator to Floor 52',
                        'content' => <<<'TXT'
The executive elevator required a black keycard. Everyone in the building knew there were only nine of them.

At two in the morning on Thursday, Ethan pushed his cart into that elevator and pressed 52. The doors closed on a stunned night guard who had never seen the light for that floor turn on.

Floor 52 was one room: a long table, forty chairs, and a wall of glass that held the whole city in it. Ethan walked the length of the table and stopped at the chair at the head, the one nobody had sat in for three years. Dust had settled in the seam of the leather. He wiped it clean with his sleeve.

His phone buzzed. M again: "Whitaker moved his shares this morning. He has the votes to take the chair on Friday. He is telling people the founder's son is dead."

Ethan looked at his reflection in the black glass - a janitor in a cap, standing where his father used to stand. "Let him say it," he typed. "It makes the correction louder."
TXT
                    ],
                    [
                        'title' => 'A Signature Worth Billions',
                        'content' => <<<'TXT'
Friday morning. Ethan came in through the loading dock with his mop, because he wanted to hear the room before it saw him.

Through the service door he listened to Whitaker open the meeting. "The Vale estate has been silent for three years. The trust holds forty-one percent and it has never once voted. Silence is consent, gentlemen. I move that we-"

"The trust votes today," Ethan said, and pushed the door open.

Forty heads turned. Someone laughed - a short, confused bark that died in the middle. Whitaker's face went through three expressions and settled on contempt. "Get out. Ruiz! Get this man out of my boardroom."

Ruiz did not move.

Ethan walked to the head of the table, set his mop against the glass wall, and took a folded document from his coverall pocket. He smoothed it on the table so everyone could see the letterhead, the seal, and the signature at the bottom. "The trust is not silent," he said. "The trust is me. Ethan Vale Cole. And the first thing it does today is read your emails out loud."
TXT
                    ],
                    [
                        'title' => 'The Boardroom Bows',
                        'content' => <<<'TXT'
Nobody breathed. Whitaker laughed again, but it came out thin. "That's a forgery. That is a man in a cleaning uniform holding a piece of paper."

"Call the registrar," Ethan said. "The number is on the second page. I'll wait. I have waited three years; I can wait four more minutes."

Somebody called. The room listened to a speakerphone confirm, in a bored voice, that the beneficial owner of forty-one percent of Vale Industries had been standing on the premises every single night since March.

Chairs scraped. Two directors stood up so fast their water glasses tipped. Whitaker sat down instead, slowly, as if his legs had been removed. "Ethan. Ethan, listen. The coffee - that was a joke, that was nothing, that was-"

"That was Tuesday," Ethan said. "Priya, come here."

The young analyst with the cracked phone edged along the wall, terrified. Ethan pulled out the chair beside his own. "You cleaned a floor you didn't dirty, in front of a man who could fire you. That's the only interview I care about." He looked up at the rest of them, and his voice did not rise at all. "Now. Let's talk about who else in this room has been kind when it cost them something."
TXT
                    ],
                ],
            ],
            [
                'title' => 'My Broke Husband Is a Billionaire',
                'slug' => 'my-broke-husband-is-a-billionaire',
                'author' => 'Elena Hart',
                'status' => 'ongoing',
                'featured' => true,
                'free' => 2,
                'cats' => ['Romance', 'Billionaire'],
                'description' => 'Clara married a man with a twelve-dollar ring, a secondhand coat, and no job her family would admit to at parties. For two years she defended him against every insult at every dinner table. She is about to learn that the husband everyone calls a freeloader signs the checks that keep her family employed.',
                'chapters' => [
                    [
                        'title' => 'The Cheap Wedding Ring',
                        'content' => <<<'TXT'
The ring cost twelve dollars. Clara Reyes knew because she had been standing next to Adam when he bought it, at a stall outside the courthouse, six minutes before their wedding.

"I can do better than this," he had said, turning it in the light. "Give me time."

"I don't want better. I want Tuesday, and you, and lunch after." She had put it on herself.

Two years later the silver had gone dull and her sister still asked, every single time, whether it turned her finger green. Clara had learned to smile through it. She worked twelve-hour shifts at the hospital, Adam did whatever it was Adam did with his laptop at the kitchen table, and the rent got paid, and that was enough.

That evening he was still at the table when she came home at eleven, three screens of numbers open, a cold cup of tea beside him. He closed the laptop the instant she walked in - the way he always did.

"Long day?" he asked.

"Sixteen hours. Mrs. Alvarez's grandson brought me cake." She kicked her shoes off. "What did you do today?"

Adam thought about it. "Some paperwork," he said. "Nothing worth talking about."
TXT
                    ],
                    [
                        'title' => 'Dinner With Her Family',
                        'content' => <<<'TXT'
The Reyes family dinner was the fourth Sunday of every month, and it was an ambush every time.

"Adam!" Clara's uncle Ronald raised his glass. "Still between opportunities?"

"Still between opportunities," Adam agreed pleasantly, and passed the bread.

Clara's sister Bianca had brought her new fiance, a regional manager with a leased German car and a laugh he used like punctuation. "You know, I could get you an interview," he said. "Entry level, obviously. Everyone starts somewhere."

"That's generous," Adam said.

"It's charity, is what it is," Ronald muttered into his wine. "Clara, sweetheart, you carry that whole apartment. A man who lets his wife pay the rent isn't a man."

The table went quiet in the way that means everyone agrees.

Clara set her fork down. "He does the shopping, the cooking, and my taxes. He sat with Dad in the hospital for nine nights when nobody else could get off work. Nobody at this table asked how Dad was doing tonight, so I'll tell you: he's fine. Adam drove him to the follow-up on Thursday."

In the car afterward, Adam drove in silence for a long time. "You don't have to fight them for me," he said finally.

"Somebody has to," Clara said. "You never fight for yourself."

He looked at the road. "Not yet."
TXT
                    ],
                    [
                        'title' => 'The Card She Threw Away',
                        'content' => <<<'TXT'
Clara found the card on a Wednesday, in the pocket of Adam's old coat, when she was pulling the laundry.

It was matte black, heavy, no bank logo she recognized. The name embossed on it was not Adam Reyes. It said, in small silver letters: A. HARTWELL.

She stood in the laundry room for a full minute. Then she put it on the kitchen counter, poured two cups of coffee, and waited three hours for her husband to come home.

He saw it and stopped in the doorway.

"Hartwell," Clara said. "As in Hartwell Group. As in the building I can see from the hospital roof."

"As in my father," Adam said quietly. "And my grandfather. And, since I was twenty-six, me."

"How much?"

"Clara-"

"How much, Adam?"

He put his keys down very carefully, as though the noise might break something. "Enough that when my father died, four hundred people showed up to tell me they loved me, and I couldn't tell which two meant it. So I put on an old coat and went to find out what my life looks like when nobody knows what I'm worth." He looked up at her. "I found you. And then I was too much of a coward to risk it."
TXT
                    ],
                    [
                        'title' => 'The Man in the Black Sedan',
                        'content' => <<<'TXT'
The black sedan had been parked across from the apartment for two days. Clara noticed it on the third.

"He's my head of security," Adam admitted. "His name is Novak. He has followed me for eleven years and he does not enjoy the neighborhood."

"You have a head of security." Clara pressed her palms against her eyes. "I made you take the bus in the rain last month."

"I like the bus."

"Nobody likes the bus, Adam."

That night her phone rang: her sister, shrieking, barely words. Bianca's fiance had been fired - not for cause, he swore, just a restructuring, the whole regional division gone in an afternoon. "It's Hartwell," Bianca sobbed. "Hartwell bought them out in March and now they're cutting everyone."

Clara looked across the kitchen at her husband, who was washing dishes in a shirt with a hole in the sleeve.

"March," she said.

Adam did not turn around. "I bought it in March," he said. "And I read every performance file myself, including the one about a regional manager who filed expense reports for dinners he never had. That decision was made in a room, Clara. Not at your family's table. I've never once used the company against them."

"But you could."

He dried his hands. "Yes," he said. "And this Sunday, they're going to find that out."
TXT
                    ],
                    [
                        'title' => "Her Husband's Real Name",
                        'content' => <<<'TXT'
The fourth Sunday came. Adam wore the old coat on purpose.

Uncle Ronald was already three glasses in when he started. "Well, the freeloader graces us. Bianca's man loses his job and this one still hasn't looked for one. Some people are just born without shame."

"Ronald," Clara's mother warned.

"No, let him talk," Adam said. He took off the coat, folded it over the back of the chair, and sat down. "Ronald, you work at Meridian Logistics. Fourteen years, warehouse operations."

"So?"

"So on Friday, Meridian's parent company approved a retention bonus for every employee over ten years of service. Yours cleared this morning. Did you check?"

Ronald pulled out his phone with a smirk that lasted right up until the screen loaded. His face emptied.

"Meridian is a Hartwell subsidiary," Adam said. "So is the hospital group that employs Clara, which is why I have never - not once - set foot in her building. My name is Adam Hartwell. I have been sitting at this table for two years, and every month you have told my wife she married beneath her."

Nobody moved. Bianca's fork hit her plate.

"I'm not here to take anything from you," Adam said, and stood, and picked up the old coat. "I just wanted her to hear one apology out loud before I stop coming." He turned to Clara. "Ready?"

She took his hand - twelve-dollar ring and all - and walked out into the evening with him.
TXT
                    ],
                ],
            ],
            [
                'title' => 'The Beggar at the Board Meeting',
                'slug' => 'the-beggar-at-the-board-meeting',
                'author' => 'Daniel Cross',
                'status' => 'ongoing',
                'featured' => false,
                'free' => 1,
                'cats' => ['CEO', 'Secret Identity'],
                'description' => 'A man in a soaked, torn jacket sits on the steps of Aldridge Tower every morning, and every morning the executives walk past him. When he finally climbs the stairs and pushes open the boardroom door, security is called before anyone thinks to ask his name. The vote they are about to take belongs to him.',
                'chapters' => [
                    [
                        'title' => 'Rain on the Steps',
                        'content' => <<<'TXT'
It rained for six days in a row, and for six days the man sat on the third step of Aldridge Tower with a paper cup beside him.

Executives learned to angle around him. One threw a coin that missed and rolled into the gutter. A woman from the twelfth floor brought him coffee twice and did not tell anyone she had done it.

His name was Samuel Aldridge Reyes, and he had not spoken it out loud in four years.

On the sixth morning, a receptionist named Dara crouched under her umbrella and held it over him. "You can't stay out here," she said. "There's a shelter on Ninth. I can walk you."

"I'm waiting for someone," he said.

"Who?"

He looked up at the tower - fifty-one floors of glass with his family's name cut into the stone above the door. "Everyone," he said. "They're all coming to work on Thursday."
TXT
                    ],
                    [
                        'title' => 'Security Escort',
                        'content' => <<<'TXT'
Thursday, 9:04 a.m. The revolving door turned and the man in the torn jacket came through it, leaving wet prints on the granite.

"Sir. Sir!" The desk guard was up in an instant. "You can't be in here."

"Fifty-first floor," Samuel said. "The quarterly vote starts at nine fifteen."

"Right, and I'm the Queen of Spain."

Two more guards came. They took his arms - not roughly, but not gently either - and turned him toward the door. That was when Dara stepped out from behind the reception desk and said, in a voice that surprised even her, "Wait."

Everyone stopped.

"He's been outside for a week," she said. "In the rain. Nobody in this building even asked him his name. So I'm asking. What's your name?"

The man in the torn jacket looked at her for a long moment. "Aldridge," he said. "It's cut into the stone over your head."
TXT
                    ],
                    [
                        'title' => 'The Vote He Owned',
                        'content' => <<<'TXT'
The boardroom door opened at nine nineteen. By then the room had already been arguing for four minutes about a resolution to sell the company's oldest division - eight hundred jobs, one line item.

"Hold the vote," Samuel said.

Interim chairman Colin Ward did not even look up from his tablet. "Security. Now."

"They're behind me," Samuel said. "They walked me up. Ask them why."

Ward looked up then. The man in the doorway was soaked through, unshaven, and holding a plastic folder against his chest the way people hold things that cannot be replaced. Two guards stood behind him, hats off.

Samuel walked to the table and put the folder down. "Founder's Class A shares," he said. "Fifty-two percent, held in my name since my father's will cleared probate. Four years ago I walked out of this building because I wanted to know whether the company could run without a man named Aldridge in the chair. It can. Badly."

"This is theater," Ward said.

"It's an audit," Samuel replied, and opened the folder. "Page one: the division you are voting to sell. Page two: the buyer. Page three: the buyer's registered directors, one of whom shares a home address with you."
TXT
                    ],
                    [
                        'title' => 'Chairman in Torn Shoes',
                        'content' => <<<'TXT'
Ward stood up so fast his chair went over. "You have no standing here. You have been gone for four years - you are a vagrant with a photocopy!"

"Then call the transfer agent," Samuel said. "You have their number. You've been begging them for my proxy since March."

Nobody called. Nobody needed to. Three directors were already reading page three, and one of them had gone the color of paper.

Samuel pulled out the chair at the head of the table and sat down in it, wet jacket and all, and the leather darkened under him. "The vote is cancelled," he said. "The division stays. Mr. Ward's resignation will be on my desk by noon, and legal will be waiting for him at the elevator."

He looked around the table at eleven faces he had known since childhood. "For six days I sat on our steps. Two people in this building spoke to me like a person - one of them brought coffee, and the other one asked my name in front of everybody when it would have been easier not to."

He nodded toward the door, where Dara stood frozen with a visitor badge in her hand.

"Get her a chair," Samuel said. "She's going to be in the room from now on."
TXT
                    ],
                ],
            ],
            [
                'title' => 'Return of the Hidden Heir',
                'slug' => 'return-of-the-hidden-heir',
                'author' => 'Victor Lang',
                'status' => 'completed',
                'featured' => false,
                'free' => 1,
                'cats' => ['Revenge', 'Billionaire'],
                'description' => 'At fourteen, Julian Reyes was driven out of the family estate with a suitcase and a lie told about his mother. Twelve years later he comes back in a borrowed coat, lets them mock him through an entire dinner, and then explains who has owned their debt since spring. Some doors are best opened from the inside.',
                'chapters' => [
                    [
                        'title' => 'Twelve Years Later',
                        'content' => <<<'TXT'
The gate was the same. That was the strange part. Julian had expected everything to be smaller, the way childhood houses always are, but the Kessler gate was exactly as tall as he remembered, and the intercom still buzzed twice before anyone answered.

"Deliveries at the side," a bored voice said.

"Julian Reyes," he said. "For dinner."

A pause. Then a different voice, sweeter, delighted. His aunt Marguerite. "Julian! Little Julian. Well. We didn't think you'd actually come."

The gate rolled open. He walked up the gravel drive in a coat that had cost forty dollars in a resale shop, past six cars that each cost more than the house he grew up in after they threw him out.

He had been fourteen. His mother had been dead nine days. They had told him the estate was insolvent, that there was nothing for him, that a boy with his surname was not a Kessler at all.

He had believed them for four years. It took him another eight to find the paperwork that proved they had lied.
TXT
                    ],
                    [
                        'title' => 'The Family That Buried Him',
                        'content' => <<<'TXT'
They seated him at the far end of the table, near the kitchen door, where the servers had to reach across him.

"So!" Marguerite beamed down the length of the table. "Tell us everything. Are you still doing that - what was it - warehouse work?"

"Logistics," Julian said.

"Logistics." His cousin Theo snorted into his glass. "That's a lovely word for a forklift."

The laughter went around the table like a plate being passed. Julian let it. He ate the soup. He answered questions about rent and buses and whether he had ever been abroad, and he watched his uncle Gregor at the head of the table not laughing at all, watching him instead with the flat attention of a man who has learned to be nervous.

"You must be short," Marguerite said kindly, near dessert. "It happens. Gregor, we should help him. A few thousand. Something to get him started."

"That's very generous," Julian said.

"Family is family," she said, and reached for her wine. "Whatever your mother did."

Julian set his spoon down. "Say the rest of that sentence, Aunt Marguerite."

The room got quiet. She didn't say it. She never did - it had always been a sentence that stopped in the middle, for twelve years, so that no one had to be caught holding it.
TXT
                    ],
                    [
                        'title' => 'The Deed to the Old House',
                        'content' => <<<'TXT'
"I brought a gift," Julian said, and put a folder on the table.

Theo laughed. "Coupons?"

"The deed to this house."

Nobody laughed at that. Gregor's hand stopped halfway to his glass.

"Kessler Estate was mortgaged in 2019 against the family holding company," Julian said. "The holding company defaulted in 2023. The debt was bought by a fund out of Singapore, then resold twice, which is a very tidy way to lose track of who is standing behind you. Uncle Gregor knows this. He's been paying interest to a name he's never met for two years."

"Northgate Capital," Gregor said hoarsely.

"That's right." Julian turned the folder around so it faced them. "I own Northgate. I have owned it since I was twenty-three. I bought your debt the spring my grandmother's letter finally cleared the courts - the letter where she left me a third of everything and you told me there was nothing."

Marguerite's smile was still on her face, but there was nobody behind it anymore.

"You were fourteen," Gregor said. "You wouldn't have understood the structure-"

"I was fourteen," Julian agreed. "I understood the gravel. I walked down it with one bag. I have thought about that gravel every day for twelve years."
TXT
                    ],
                    [
                        'title' => 'A Toast Among Wolves',
                        'content' => <<<'TXT'
Theo went for anger first, because that was the only tool he had ever needed. He shoved his chair back. "You think you can walk in here and threaten this family? My father built-"

"Your father inherited," Julian said. "Sit down, Theo."

Theo sat down. He looked as surprised about it as everyone else.

Julian stood instead, and lifted his glass. "A toast. To Grandmother Ilse, who wrote a letter. To my mother, who signed nothing and was blamed for everything. And to this family, which has ninety days."

"Ninety days for what?" Marguerite whispered.

"To vacate, if I call the loan." He let that sit. "I'm not going to call it tonight. I want you to spend those ninety days doing something you have never done once: worrying about where you'll sleep."

Gregor put both hands flat on the table. "What do you want, Julian? Say it plainly."

"Two things. Marguerite finishes her sentence in front of everyone here - out loud, the whole lie, exactly as she told it to me when I was fourteen." Julian set his glass down. "And then she tells me where my mother is buried. Because you never did."

The clock in the hall ticked eleven times before anyone spoke.
TXT
                    ],
                    [
                        'title' => 'The Name on the Building',
                        'content' => <<<'TXT'
The cemetery was ninety minutes north, a small one behind a stone church, and the marker was flat in the grass with only her first name on it. No dates. Nothing to connect her to anyone.

Julian knelt in the wet grass for a long time.

"I bought the company," he told her. "Not to burn it. To fix your name on it." He brushed the moss out of the letters with his thumb. "It's going to say Reyes. On the building, on the door, on the annual report they hate reading. Every one of them is going to have to say it out loud at meetings for the rest of their careers."

Behind him, Gregor waited by the car with his hat in his hands - an old man now, in the daylight, without a table to sit at the head of.

"I'm signing the transfer Monday," Julian said, standing. "You keep the house. You keep it as a tenant, and the rent goes into a scholarship in her name."

"And Theo? Marguerite?"

"They can work," Julian said. "I hear logistics is hiring."

He got in the car. Through the rear window the church got smaller, and for the first time in twelve years he did not feel like someone walking away from something. He felt like someone leaving on purpose.
TXT
                    ],
                ],
            ],
            [
                'title' => 'She Laughed at His Old Car',
                'slug' => 'she-laughed-at-his-old-car',
                'author' => 'Sophie Bennett',
                'status' => 'ongoing',
                'featured' => false,
                'free' => 3,
                'cats' => ['Romance', 'Revenge'],
                'description' => 'Nora took one look at the rusted sedan pulling into the valet lane at the Ardent and laughed loud enough for the whole terrace to hear. The driver only smiled and handed over his keys. By dessert she will have learned that the man in the twenty-year-old car is the reason the Ardent has a valet lane at all.',
                'chapters' => [
                    [
                        'title' => 'The Rusty Sedan',
                        'content' => <<<'TXT'
The car came up the Ardent's white gravel drive making a sound like a cough, and the entire terrace turned to look.

It was a beige sedan, maybe twenty years old, with a dent in the rear door and a side mirror held on with tape. It stopped in the valet lane between a silver coupe and something Italian and low.

Nora Whitfield laughed. She did not mean to be cruel about it - she was three drinks in and it was funny - but it came out loud, and once it was out, the terrace laughed with her.

"Oh no," her friend Camille said, delighted. "Somebody's grandfather is lost."

The driver got out. Late thirties, plain gray shirt, no watch. He heard all of it. He looked up at the terrace, found Nora's face in the crowd, and smiled - not embarrassed, not angry. Amused.

Then he tossed his keys to the valet and said something that made the young man laugh, and walked in through the front doors that nobody walks in through without a reservation.

"Huh," Camille said. "He knew the valet's name."
TXT
                    ],
                    [
                        'title' => 'The Valet Who Knew',
                        'content' => <<<'TXT'
Nora went down to the lane an hour later, ostensibly for air.

"That car," she said. "The old one. Whose is it?"

The valet, a kid of maybe twenty, was polishing the beige sedan's hood with a cloth he had clearly brought from home. "Mr. Ardent's."

"Mister- the hotel is called the Ardent."

"Yes, ma'am."

Nora waited. The kid kept polishing.

"He drives that," she said, "and he owns this."

"It was his dad's car," the valet said. "He put himself through school in it. He says the day he stops fitting in it is the day he's become somebody he wouldn't have liked at nineteen." He shrugged. "Also, he pays for my sister's meds. So I keep it clean."

Nora looked up at the terrace, where forty people who had laughed with her were still drinking on a rooftop that belonged to the man in the gray shirt.

"What's his first name?" she asked.

"Leo," the valet said. "And he's in the dining room. Table four. He always sits where he can see the lane."
TXT
                    ],
                    [
                        'title' => 'Table for One at Ardent',
                        'content' => <<<'TXT'
He was eating alone, reading something on a folded page, and he looked up before she had finished crossing the room.

"You're the laugh," Leo said.

"I'm the laugh," Nora admitted. "I'd like to apologize badly enough that I'm willing to do it standing up in front of a room."

"Sit down instead. Standing apologies are for the person apologizing." He moved the page aside. "What did you think when you saw the car?"

"That someone had made a mistake coming here."

"And now?"

"That I did." She sat. "My father lost his business when I was nineteen. Nine years of a certain kind of dinner party where people stop remembering your name. I got very good at being the one laughing so nobody could laugh at me first." She turned her glass. "That's an explanation, not an excuse."

Leo studied her for a moment. Then he pushed the bread basket across the table.

"My mother cleaned rooms in a hotel two miles from here," he said. "Not this one. I bought this one because the manager there wouldn't let her use the front door. So - I do have opinions about who laughs at whom. But I also drove a loud car into a quiet driveway. Some of that was on purpose."
TXT
                    ],
                    [
                        'title' => 'Keys to the Penthouse',
                        'content' => <<<'TXT'
Camille found them at eleven, when the terrace had thinned out. "Nora! There you are. We're going up to the penthouse suite, Bryce says he can get us in-"

"Bryce cannot get you in," Leo said mildly.

Camille finally focused on him. "And you are?"

"The person who decides who gets in."

Nora watched her friend's face do the whole journey - amusement, confusion, the slow arrival of the name over the door - and felt something in her chest that was almost pity.

"Oh my God," Camille breathed. "You're- Nora, do you know who this is?"

"I do now," Nora said.

Leo stood and put his napkin down. "Your friend has the suite if she wants it," he said to Nora. "That's not a favor to her; it's already paid for by her father's company. But I'd rather drive you home."

"In the car?"

"In the car," he said. "The heater doesn't work and the radio only gets one station."

Nora picked up her bag. "Then I'll need a coat," she said, "and a second chance at a first impression."

Behind them, the terrace watched a woman in an expensive dress climb into a twenty-year-old sedan, and for once nobody laughed at all.
TXT
                    ],
                ],
            ],
            [
                'title' => 'Son-in-Law of the Silver Empire',
                'slug' => 'son-in-law-of-the-silver-empire',
                'author' => 'Adrian Wolfe',
                'status' => 'ongoing',
                'featured' => false,
                'free' => 1,
                'cats' => ['Family Drama', 'Billionaire'],
                'description' => 'For three years the Sinclair family has called Ray Bishop the freeloader who married their daughter - seated at the children table, sent for the ice, blamed for the bill. On the night their company needs a signature only one man in the country can give, that man walks in through the kitchen door wearing an apron. He is in no hurry at all.',
                'chapters' => [
                    [
                        'title' => 'The Useless Son-in-Law',
                        'content' => <<<'TXT'
Ray Bishop had washed dishes at every Sinclair family gathering for three years, and he had stopped being asked to.

Tonight it was forty guests and a catering staff of six, and he was still at the sink, because his mother-in-law Vivienne had a way of saying "Ray, sweetheart, the good glasses" that made it a fact rather than a request.

"He does it well, at least," Ray's brother-in-law Damian said, loud, from the doorway. "That's something. Every family needs somebody good with their hands."

"Every family needs somebody who shows up," Ray said, and set a glass in the rack.

Damian came in and leaned against the counter. "Three years, Ray. Three years and you have never once contributed. My sister supports you. Do you know what that looks like?"

Ray thought about the four hundred and twelve million dollar acquisition he had approved that morning from a laptop in the laundry room.

"I imagine it looks like this," he said, and reached for another glass.
TXT
                    ],
                    [
                        'title' => 'Birthday of the Old Matriarch',
                        'content' => <<<'TXT'
Grandmother Sinclair turned eighty-four in September, and she was the only person in the family who called Ray by his first name without an insult attached to it.

"Sit by me," she ordered, when he came out with the cake.

"Grandmother, he's got the plates-" Vivienne began.

"He can hold plates sitting down."

So Ray sat, and the old woman held his wrist with a hand like paper. "They're awful to you," she said, not quietly.

"They're scared," Ray said. "The company's in trouble. Scared people need somebody to be above."

She looked at him with eyes that had missed very little in eighty-four years. "You know about the company."

"I know a little."

"You know more than a little." She patted his hand. "My husband started that firm with a truck and a bad back, and these children of mine are going to lose it by Christmas. Whatever you're waiting for, Raymond - don't wait so long that there's nothing left to save."

At the far end of the table, Damian raised his glass and said, "To Grandmother! And to Ray, who I hear is between opportunities again."

Ray smiled and drank to it.
TXT
                    ],
                    [
                        'title' => 'The Contract Nobody Could Sign',
                        'content' => <<<'TXT'
The crisis arrived on a Thursday in the form of a letter with a silver crest.

Sinclair Freight had bet everything on a port contract, and the port had been bought - the whole terminal, the cranes, the leases - by an entity called Silver Group. Sinclair's shipping rights would terminate in thirty days unless renewed by the new owner.

"So we call them," Vivienne said, pacing the living room. "We call them and we make an appointment."

"I've called eleven times," Damian said. His tie was undone and his hands were shaking. "There's no number that reaches anyone. There's a general counsel who says the chairman signs personally and the chairman does not take meetings."

"Then who is the chairman?"

"Nobody knows! There's a name on the filings and the name is - it's initials, it's a holding structure, it's-" He threw the letter down.

From the kitchen doorway, drying his hands on a dish towel, Ray said, "Thirty days is a long time."

Damian rounded on him. "Do you have the faintest idea what's happening here? We could lose everything. Everything. So unless you can conjure the chairman of Silver Group out of your apron, shut your mouth."

"Okay," Ray said, and went back to the dishes.
TXT
                    ],
                    [
                        'title' => 'Silver Group Calls',
                        'content' => <<<'TXT'
On day twenty-nine, at 8:40 in the morning, three black cars came up the Sinclair driveway.

Vivienne saw them from the window and nearly dropped her cup. "They came. Damian - they came! Get your jacket, get the folder, get-"

Eleven people got out. Suits, badges, one woman carrying a case cuffed to her wrist. The lead, a silver-haired man of about sixty, walked past Damian's outstretched hand entirely.

"Good morning," he said. "Ms. Okafor, general counsel, Silver Group. Is he here?"

"Is who here?" Vivienne said.

The counsel looked past her, into the house, where Ray Bishop was standing in the hallway in an apron with a coffee cup in one hand.

Every person in the delegation straightened at once.

"Chairman," she said. "We apologize for the intrusion at your home. The Meridian file needs your signature this morning; the port authority moved the deadline."

The Sinclair family stood absolutely still in their own foyer.

Ray took the pen. "Kitchen table," he said. "The good one is being used for a folder nobody's going to need now."
TXT
                    ],
                    [
                        'title' => 'Kneeling in the Rain',
                        'content' => <<<'TXT'
It took Damian eleven minutes to break.

He followed Ray out to the driveway in the rain, past the cars, past the staff, without a coat. "Ray. Ray! Three years - listen, three years of jokes, that's brothers, that's just how families-"

"You told my wife she'd wasted her life," Ray said, without turning around. "At her own birthday. In front of forty people."

Damian went down on one knee on the wet gravel. Actually down, both hands out. "Please. Eight hundred people work for us. Eight hundred families. Punish me, not them - I'll resign, I'll do anything, I'll wash your dishes for the rest of my life-"

"Get up," Ray said. "You'll ruin the suit and you'll still owe me an apology you haven't figured out yet."

He turned around. Behind Damian, in the doorway, his wife Nadia stood with her arms crossed, watching her family understand her husband for the first time.

"The contract renews," Ray said. "Not for you. For the eight hundred. It renews with an operations review, new board seats, and a name change." He handed the signed page to his counsel without looking at it. "Silver Group has one condition, and it is not negotiable."

"Anything," Damian said.

"Grandmother Sinclair goes on the board," Ray said. "She's the only one of you who ever offered me a chair."
TXT
                    ],
                ],
            ],
            [
                'title' => 'The Delivery Boy Who Bought the Mall',
                'slug' => 'the-delivery-boy-who-bought-the-mall',
                'author' => 'Ryan Cole',
                'status' => 'completed',
                'featured' => false,
                'free' => 2,
                'cats' => ['Rags to Riches', 'Secret Identity'],
                'description' => 'Toby has delivered lunch to the Crestline Mall management office every day for a year, and every day the manager makes him wait by the service elevator. On Friday the mall goes to auction, and the winning bidder walks into the room in a delivery jacket still damp from the rain. He keeps the jacket on.',
                'chapters' => [
                    [
                        'title' => 'Order 47',
                        'content' => <<<'TXT'
Order 47 was the same every weekday: two chicken salads, one soup, delivered to the fourth-floor management office of Crestline Mall by twelve fifteen or the tip disappeared.

Toby Nunez had never once been late. He had also never once been allowed to use the main elevators.

"Service lift's around back," the receptionist said, the way she said it every day, not unkindly, just automatically. "You know the way."

He knew the way. Two hundred and eleven steps from the loading dock, past the dumpsters, up the freight elevator that smelled like cardboard. He had counted them in his first week and never stopped counting.

He set the bags on the counter at 12:11.

"You're wet," the receptionist observed.

"It's raining," Toby said. "It does that."

Inside the manager's office, someone laughed at something, and a voice said, "-because the whole property's underwater, that's why. Somebody's going to get it for scrap in March."

Toby signed the delivery sheet, put his pen away, and did not look up.
TXT
                    ],
                    [
                        'title' => 'The Receipt He Tore Up',
                        'content' => <<<'TXT'
In February, mall manager Curtis Pike tore up a receipt in Toby's face.

"You're four minutes late."

"The freight elevator was locked out for maintenance. I took the stairs. Four flights."

"Then you're four minutes late." Pike tore the slip in half and let it fall. "No signature, no payment. Your shop can eat it."

"That's fourteen dollars out of my day," Toby said quietly.

"That's a lesson," Pike said. "Free. You're welcome."

The receptionist, Elena, waited until Pike's door closed, then paid the fourteen dollars out of her own purse and made Toby take it. "He does this," she said. "He did it to the flower girl twice."

"Why do you stay?" Toby asked.

"Two kids, and this is the only place that let me start at ten so I can do the school run." She shrugged. "You get used to the stairs."

Toby looked at the torn receipt on the floor and, for the first time in a year, picked it up and put it in his pocket instead of the bin.
TXT
                    ],
                    [
                        'title' => 'Bidding at Noon',
                        'content' => <<<'TXT'
Crestline Mall went to auction on a Friday at noon, in the conference room on the fourth floor, because the receiver thought it would be poetic.

Nineteen registered bidders. Curtis Pike stood at the back with his arms folded, telling anyone who would listen that he had a syndicate lined up and would be signing his own paychecks by dinner.

The bidding opened at eleven million. It moved fast, then slower, then stalled at twenty-six.

"Twenty-six million, going once."

"Thirty-one," said a voice from the doorway.

Every head turned. A young man in a soaked delivery jacket stood at the back of the room, helmet under one arm, holding a phone.

Pike started laughing before he even registered the face. "Get out! This is a closed - Elena, who let the food in?"

The receiver looked at his register. Then he looked at the young man. "Mr. Nunez," he said. "Bidder eleven. You're confirmed with proof of funds."

The room went so quiet you could hear rain on the skylights.

"Thirty-one," Toby said again. "And I'd like the record to show I came up the freight elevator."
TXT
                    ],
                    [
                        'title' => 'New Owner, Same Uniform',
                        'content' => <<<'TXT'
Nobody outbid him. It closed at thirty-one million to a twenty-six-year-old in a delivery jacket, and the murmur that went around the room followed Toby all the way to the front.

He had made his money on a routing app - the little program he wrote in his first months on the bike, because the dispatch system was so bad it cost him forty minutes a day. Three delivery companies had licensed it. Then eleven. He had kept riding, because he liked riding, and because the office on the fourth floor had taught him something about who people are when they think you are nobody.

Curtis Pike was still standing at the back, gray-faced. "Toby- Mr. Nunez. Listen. About the receipts-"

Toby took the torn receipt out of his wallet, still in two pieces, taped together. "Fourteen dollars," he said. "Elena paid it. You owe her, not me."

"I'll pay her back today-"

"You'll pay her back today, and then you'll clear your desk, because I read the tenant complaints before I bid." Toby turned to the receiver. "First order of business: the freight elevator gets fixed and the main lifts open to delivery staff. Second: everybody who works in this building gets a name badge with their actual name on it."

He looked at Elena, who was crying and trying not to.

"Third," he said, "the management office needs a new manager. Somebody who knows where the stairs go."
TXT
                    ],
                ],
            ],
            [
                'title' => 'Ten Years Poor, One Day King',
                'slug' => 'ten-years-poor-one-day-king',
                'author' => 'Nathan Reed',
                'status' => 'ongoing',
                'featured' => false,
                'free' => 1,
                'cats' => ['Rags to Riches', 'Second Chance'],
                'description' => 'Silas Boone spent ten years in a basement apartment eating instant noodles, taking the blame for a fraud he did not commit. Then a dead mentor leaves him a letter, a key, and forty percent of the company that ruined him. Ten years is a long time to think about exactly what you would do with one day.',
                'chapters' => [
                    [
                        'title' => 'Ten Years of Instant Noodles',
                        'content' => <<<'TXT'
The basement apartment on Kessel Street had one window at ankle height, and through it Silas Boone had watched ten years of shoes go by.

He ate the same dinner most nights. He worked nights stocking shelves and days doing bookkeeping for a laundromat, a barber, and a woman who sold cakes out of her kitchen. Nobody who hired him asked about the gap in his resume, because nobody who hired him read one.

Ten years ago he had been a thirty-year-old analyst at Corvin Partners with a corner desk and a wedding scheduled for June. Then eighteen million dollars moved through accounts with his login on them, and the firm's founder went on television and said the word "rogue" eleven times.

There was no trial. There was a settlement, a bar from the industry, and a June that never happened.

"You should move," said Mrs. Adeyemi from upstairs, every few months. "Somewhere with a real window."

"Not yet," Silas said, every time.

He never explained why. The truth was small and slightly embarrassing: he was waiting to be believed.
TXT
                    ],
                    [
                        'title' => 'The Letter From the Dead Man',
                        'content' => <<<'TXT'
Arthur Reyland died on a Tuesday in March, at eighty-one, and his lawyer found Silas at the laundromat.

"You're a difficult man to locate," she said, stepping over a basket.

"I'm in the phone book," Silas said. "Nobody looks in the phone book."

The letter was handwritten, four pages, and it started: "Silas - I was a coward for nine years and a dying man for one, and only one of those is an excuse."

Arthur had been the firm's senior partner. He had known. He had watched Marcus Corvin's son move the money through a junior analyst's credentials, and he had said nothing, because his name was on the door and his wife was sick and the firm was all there was.

*I could not give you those ten years back,* the letter said. *So I have spent them buying something for you instead. Quietly. Through four names that are not mine. Ask my lawyer what a forty-one percent block of Corvin Partners is worth, and then ask her whose it is now.*

Silas read the last page twice, standing in the smell of detergent and hot lint.

"Mr. Boone," the lawyer said carefully. "Are you all right?"

"Ten years," Silas said. "I had a whole speech ready and I can't remember any of it."
TXT
                    ],
                    [
                        'title' => 'One Signature, One Empire',
                        'content' => <<<'TXT'
The transfer took nine days and a room full of people being extremely polite to a man in a jacket from a discount store.

"You'll want to hold the shares through a vehicle," the lawyer said. "For discretion."

"No," Silas said. "Put my name on it. All of it. Spelled correctly."

"They will know within the hour."

"That's the point."

He signed at 10:41 on a Thursday morning. By 11:15 his phone - a cracked handset with a prepaid plan - had rung fourteen times from numbers he had not heard from in a decade.

He did not answer any of them. He walked to the laundromat, finished the March books for Mrs. Okonjo, refused payment for the first time ever, and told her he would be away for a few days.

"Away where?" she asked.

Silas looked out the window at the towers downtown, where a man had once said the word "rogue" on television eleven times.

"Work," he said.
TXT
                    ],
                    [
                        'title' => 'The Men Who Framed Him',
                        'content' => <<<'TXT'
The Corvin Partners boardroom smelled exactly the same. That was the thing that nearly undid him - not the faces, the smell. Carpet cleaner and old coffee.

Marcus Corvin stood up with both arms open. "Silas! My God. Look at you. Sit, sit - listen, whatever happened back then, that was lawyers, that was insurance, that was never personal-"

"Where's Peter?" Silas asked.

The room shifted. Peter Corvin, forty-four now, sat at the far end of the table looking at his hands.

"Peter used my login on the ninth of April," Silas said. "Twice. Once at 2:14 in the morning, from the eleventh floor, while I was at my mother's funeral in another state. Arthur Reyland wrote it down that week, notarized it, and left it in a safe for ten years." He put the notarized page on the table. "He was a coward. But he was a coward with good records."

Marcus's face did something complicated. "Silas. Son. We can settle this quietly-"

"You said rogue eleven times," Silas said. "I counted. It was the only thing I had to do that year."
TXT
                    ],
                    [
                        'title' => 'King for a Day, King for Good',
                        'content' => <<<'TXT'
He held forty-one percent. Arthur's widow held nine and voted with him. It took four minutes.

"Marcus Corvin is removed as chairman, effective immediately," Silas said. "Peter Corvin is suspended pending a criminal referral - the file goes to the regulator this afternoon, not as leverage, as a filing. There is nothing to negotiate."

Nobody argued. Twelve directors who had not returned his calls in ten years sat with their hands folded like schoolchildren.

"And the name?" someone asked at last. "The firm is Corvin Partners. That's on every contract we hold."

Silas considered it. Outside the window the city was doing what it always did, indifferent to all of it.

"Leave it," he said. "I don't want my name on a door. I've had ten years to learn what a name is worth when somebody else can take it off you in an afternoon."

He stood, picked up his cheap jacket, and paused at the door.

"One more thing. There's a bookkeeping vacancy in this building - junior, nothing glamorous. I want it filled by somebody with a ten-year gap in their resume. I want that written into the posting." He put the jacket on. "Somebody should be waiting for a phone call that actually comes."
TXT
                    ],
                ],
            ],
            [
                'title' => 'My Landlord Is a Secret CEO',
                'slug' => 'my-landlord-is-a-secret-ceo',
                'author' => 'Grace Miller',
                'status' => 'ongoing',
                'featured' => false,
                'free' => 2,
                'cats' => ['Romance', 'CEO'],
                'description' => 'Mia pays four hundred a month for a walk-up with a leaking sink and a landlord who fixes it himself in a paint-stained hoodie. She has told him everything about the terrifying job interview she is preparing for - including exactly what she thinks of the company CEO. On Monday she finds out he was taking notes.',
                'chapters' => [
                    [
                        'title' => 'The Landlord Who Fixed Sinks',
                        'content' => <<<'TXT'
The sink in apartment 3B had been dripping since Tuesday, and by Thursday Mia Delgado had put a saucepan under it and learned to sleep through the sound.

She called the number on the fridge at nine. The landlord knocked at nine forty with a toolbox.

"You could send someone," she said, holding the door. "Landlords send people."

"I am someone." Sam Okoye was maybe thirty-five, in a hoodie with paint on the sleeve, and he got under her sink without being asked twice. "Also the plumber charges ninety dollars to tighten a nut, and I refuse to participate."

Mia made tea because it seemed rude not to. Through the cabinet door he asked about her week, and because he actually waited for the answer, she told him: the applications, the rejections, the interview she had finally landed at Halcyon Systems.

"Halcyon," Sam said, from under the sink. "Big place."

"Huge. Terrifying." She sat on the floor with her back against the fridge. "It's my sixth interview this year. If this one doesn't land, I move home."

The dripping stopped. Sam slid out, wiped his hands, and looked at her for a second longer than a landlord needs to.

"It'll land," he said.
TXT
                    ],
                    [
                        'title' => 'Rent, Late Again',
                        'content' => <<<'TXT'
The rent was due on the first, and on the third Mia slid an envelope under his door with forty dollars missing and a note that said: the rest on Friday, I'm sorry, I'm so sorry.

He knocked twenty minutes later holding the forty dollars.

"I can't take this," she said.

"You didn't take it. I never had it." He handed the bills over. "Your interview's Monday, correct? You'll need to eat between now and then. People interview badly when they're hungry; I have data."

"You have data."

"Anecdotes, arranged confidently." He leaned on the doorframe. "Tell me about the company. Practice on me."

So she did. She told him about Halcyon's product line and their expansion, and then, because it was late and she was tired and he was easy to talk to, she told him the rest.

"Their CEO gives me the creeps," she said. "There's no photo of him anywhere. Not one interview. Either he's hiding something or he's one of those tech ghosts who thinks being mysterious is a personality."

Sam's expression did something small and unreadable.

"Or," he said, "he had a father who was famous, and watched what it cost him."

"That's very generous of you."

"I'm a generous man," he said. "Friday's fine for the forty dollars. Sunday's fine. Whenever."
TXT
                    ],
                    [
                        'title' => 'The Interview Panel',
                        'content' => <<<'TXT'
Monday. Fourteenth floor. Mia had ironed the only blazer she owned and rehearsed her answers on the bus twice.

The first two interviewers were fine - technical, fast, fair. Then the panel head, a man named Grier with a very white smile, leaned back and looked at her resume like it smelled.

"Two years at a nonprofit. A gap. And a - sorry, is this a laundromat?"

"Bookkeeping for small businesses," Mia said. "Nine clients. I built their inventory system."

"Charming." Grier turned the page. "Look, I'll be honest, we usually take from four schools and yours isn't one of them. What makes you think you belong in this building?"

Mia opened her mouth to be gracious, and then something in her simply declined to be.

"Because I've read your last three product releases and two of them shipped with the same accessibility bug," she said. "It's in your public tracker. Nobody on your team of four-school hires has closed it in eleven months. I could close it in a week."

The room got very cold.

And then the door at the back of the room opened, and a voice said, "She's right about the bug. It's been fourteen months, actually."
TXT
                    ],
                    [
                        'title' => 'Two Keys, One Door',
                        'content' => <<<'TXT'
Everyone stood up. Everyone except Mia, who had turned around in her chair and gone completely still.

Sam was in a suit. It was a very good suit, and it fit him badly around the shoulders the way clothes fit people who do not enjoy them.

"Sit," he told the panel. "Grier, you don't need to be in the building this afternoon, we'll talk at four." He came around the table. "Ms. Delgado. Hello."

"You fixed my sink," Mia said.

"I did."

"You fixed my sink and you took the rent late and you sat on my floor while I called you a tech ghost with no personality."

"You said mysterious," Sam said. "I've been repeating it to myself all weekend, so it made an impression."

One of the interviewers made a small strangled sound.

"My father built this company and put his face on every wall in it," Sam said. "When he died I took the photos down, because I wanted to know what people said in rooms when they thought nobody who mattered was listening." He looked at Grier's empty chair. "Now I know."

Mia stood up slowly. "I'm not taking a job because my landlord likes me."

"Good. You're not getting one." He handed her a card. "You're getting a second interview on Wednesday with the accessibility team, and if you close that bug in a week I'll be extremely annoyed, because I told them a month."

At the door she stopped. "The sink's dripping again."

"I know," Sam said. "I'll be by at seven."
TXT
                    ],
                ],
            ],
            [
                'title' => "The Pauper's Revenge Empire",
                'slug' => 'the-paupers-revenge-empire',
                'author' => 'Lucas Grant',
                'status' => 'completed',
                'featured' => false,
                'free' => 1,
                'cats' => ['Revenge', 'Second Chance'],
                'description' => 'Owen Hale slept under the bridge across from the tower that used to carry his name, wearing the coat he was wearing the day his partner signed him out of his own company. He spent four winters there learning who the city forgets. Then he started buying his old life back, one bad debt at a time.',
                'chapters' => [
                    [
                        'title' => 'The Cardboard Bed',
                        'content' => <<<'TXT'
Under the Third Street overpass, Owen Hale had a spot with a good angle: he could see the whole east face of Hale Tower, forty-two floors, lit up all night like nobody in it ever slept.

He had built that building. Technically, he had signed the loan that built it, which in this city amounts to the same thing.

"You're staring at it again," said Franklin, who slept two pillars down and had been a welder for thirty years before his hands went. "Man, that building don't love you back."

"I know."

"You come out here every winter and you look at it like it owes you money."

"It does," Owen said.

Franklin laughed until he coughed. "Sure. And I'm owed a boat."

That was the first winter. In the second, Owen started keeping notes on the back of shelter intake forms - who owned what, which shell company had bought the parking structure, which restructuring lawyer came out of the tower at seven every night and got into a car with a driver.

By the third winter, he had four hundred pages and a plan.
TXT
                    ],
                    [
                        'title' => 'The Partner Who Signed Him Away',
                        'content' => <<<'TXT'
It had taken Dominic Reyner one afternoon and one signature page.

Owen still remembered the smell of the conference room, and the way Dominic had slid the document across without meeting his eyes. "It's a formality, O. Bridge financing. If we don't close today the whole thing unwinds."

Owen had signed it. He had been awake for two days; his wife had left in August; the company was everything and Dominic had been his roommate at nineteen.

Page eleven converted his equity to a personal guarantee. Page fourteen assigned his voting rights during any default. By March there was a default, engineered from the inside, and by April there was no Owen Hale on any document anywhere - only a debt with his signature on it and a tower with his name on the door because changing the signage was expensive.

They gave him a box for his desk. Security walked him out through the lobby he had chosen the marble for.

Four years later, from under the overpass, he watched Dominic's car pull out of the garage at seven eleven, the same as every night.

"He's not even careful anymore," Owen told Franklin.

"That's how you know he thinks you're dead," Franklin said.
TXT
                    ],
                    [
                        'title' => 'A Shell Company Named Lazarus',
                        'content' => <<<'TXT'
It started with eleven thousand dollars: a settlement from a construction injury Owen had never filed for, and a lawyer at a free clinic who filed it for him.

He did not rent an apartment. He rented a mailbox and registered a company: Lazarus Holdings LLC, sole member, one seat, no employees.

"Why that name," asked Nadia Whitcomb, the clinic lawyer, who had started staying late for reasons she declined to examine.

"Because it's funny," Owen said, "and because when Dominic finally sees it on a filing, I want him to spend one entire second wondering."

The first purchase was a defaulted equipment lease - eleven cranes and a maintenance contract - bought for nine cents on the dollar because nobody wanted the storage costs. The second was the mechanic's lien on the parking structure. The third was a stack of unpaid subcontractor invoices from the tower's own retrofit, which Owen bought from the subcontractors themselves, at full value, because they were owed and he had stood in a shelter line with two of them.

"You paid full price," Nadia said, staring at the ledger. "That's terrible business."

"It's excellent business," Owen said. "Those men will testify."
TXT
                    ],
                    [
                        'title' => 'Buying the Debt',
                        'content' => <<<'TXT'
By the fourth year, Lazarus Holdings owned nineteen separate claims against Hale Tower's operating entity, and not one of them was large enough for anyone to notice.

That was the trick. Dominic watched for a rival. He did not watch for a landlord's plumber, a crane lease, a lien on a loading dock.

"They'll consolidate," Nadia warned. "The second they see the pattern, they'll refinance and pay you all out at par."

"With what money? They're leveraged eleven ways." Owen spread the filings across her office floor, which had become their conference room. "The refinancing depends on the tower's occupancy certificate. The certificate depends on the retrofit sign-off. The sign-off is held by the subcontractors nobody paid."

Nadia sat back on her heels. "Who now work for you."

"Who now believe someone finally paid them," Owen corrected. "There's a difference and it matters."

She looked at him for a long moment - the man who still owned two shirts, who had slept under an overpass for four winters, sitting cross-legged on the floor of a legal aid clinic taking a tower apart with a pencil.

"When this is over," she said, "you're getting a bed."

"When this is over," Owen said, "Franklin's getting one too."
TXT
                    ],
                    [
                        'title' => 'The Auction of Hale Tower',
                        'content' => <<<'TXT'
The receiver's auction was held in the tower's own atrium, which Owen appreciated for the symmetry.

Dominic Reyner arrived in a coat worth more than the car Owen had lived out of in year two. He had a syndicate, a press person, and the confident posture of a man who has never once been surprised in public.

He did not recognize the bidder in seat eleven until the third round.

"Forty-one million," Owen said.

Dominic turned. The whole atrium watched him do the arithmetic - the beard, the cheap suit, the face he had seen every day for nineteen years and not once in the last four.

"Owen," he said. It came out as almost nothing.

"Hello, Dom."

"You can't- you have no capital, you have nothing, you're-"

"I'm nineteen liens, a crane lease, and forty-one signed affidavits from men you didn't pay," Owen said. "Also, forty-one million. Your syndicate's on the phone right now finding out that their financing was contingent on an occupancy certificate that I control."

Dominic's phone rang. He looked at it, and did not pick it up, because he already knew.

"Going twice," the receiver said.
TXT
                    ],
                    [
                        'title' => 'Empire From Ashes',
                        'content' => <<<'TXT'
Owen did not change the name on the door. It already said Hale.

He changed other things. The ground floor retail became a clinic, the one Nadia had worked at for eleven years out of a leaking storefront on Ninth. The parking structure's third level was leased for a dollar to a shelter services group, with heat and lockers and doors that opened from the inside.

Franklin got a bed on the eighth floor of a converted building two blocks north, and complained about the mattress for a year and a half, which Owen understood to be gratitude.

Dominic Reyner's criminal referral took nineteen months. Owen sat in the courtroom for every day of it and did not once say anything to him. Afterward, on the steps, Dominic finally asked the question he had clearly been rehearsing.

"Four years under a bridge. Was it worth it? For this?"

Owen looked up at the tower.

"You think I did it for the building," he said. "I did it because for four winters, nobody in that building would look at me. Now everybody in it works in a place where the front door is open." He put his hands in the pockets of the old coat. "The building was just how I got the deed to the door."
TXT
                    ],
                ],
            ],
        ];
    }
}
