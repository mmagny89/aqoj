<?php

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:games:seed', description: 'Importe une sélection de jeux populaires')]
class SeedGamesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameRepository $gameRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seed — jeux populaires');

        $games = $this->getGamesData();
        $imported = 0;
        $skipped = 0;

        foreach ($games as $data) {
            if ($this->gameRepository->findOneBy(['bggId' => $data['bggId']])) {
                $skipped++;
                continue;
            }

            $game = (new Game())
                ->setBggId($data['bggId'])
                ->setName($data['name'])
                ->setDescription($data['description'] ?? null)
                ->setMinPlayers($data['minPlayers'])
                ->setMaxPlayers($data['maxPlayers'])
                ->setPlayingTime($data['playingTime'])
                ->setComplexity($data['complexity'])
                ->setCategories($data['categories'])
                ->setMechanics($data['mechanics'])
                ->setImageUrl(null)
                ->setYearPublished($data['yearPublished'] ?? null)
                ->setRatingBgg($data['ratingBgg'] ?? null)
                ->setSource('seed')
                ->setLastSyncedAt(new \DateTimeImmutable());

            $this->em->persist($game);
            $imported++;
        }

        $this->em->flush();

        $io->success("{$imported} jeux importés, {$skipped} déjà présents.");
        return Command::SUCCESS;
    }

    private function getGamesData(): array
    {
        return [
            ['bggId'=>'13','name'=>'Catan','minPlayers'=>3,'maxPlayers'=>4,'playingTime'=>90,'complexity'=>2.33,'categories'=>['Negotiation','Territory Building'],'mechanics'=>['Dice Rolling','Trading'],'yearPublished'=>1995,'ratingBgg'=>7.14],
            ['bggId'=>'822','name'=>'Carcassonne','minPlayers'=>2,'maxPlayers'=>5,'playingTime'=>45,'complexity'=>1.92,'categories'=>['City Building','Medieval'],'mechanics'=>['Tile Placement','Area Influence'],'yearPublished'=>2000,'ratingBgg'=>7.42],
            ['bggId'=>'9209','name'=>'Ticket to Ride','minPlayers'=>2,'maxPlayers'=>5,'playingTime'=>75,'complexity'=>1.86,'categories'=>['Trains','Routes'],'mechanics'=>['Set Collection','Route Building'],'yearPublished'=>2004,'ratingBgg'=>7.43],
            ['bggId'=>'30549','name'=>'Pandemic','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>60,'complexity'=>2.41,'categories'=>['Medical','Cooperative'],'mechanics'=>['Cooperative','Hand Management'],'yearPublished'=>2008,'ratingBgg'=>7.60],
            ['bggId'=>'36218','name'=>'Dominion','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>30,'complexity'=>2.35,'categories'=>['Card Game','Medieval'],'mechanics'=>['Deck Building','Hand Management'],'yearPublished'=>2008,'ratingBgg'=>7.61],
            ['bggId'=>'68448','name'=>'7 Wonders','minPlayers'=>2,'maxPlayers'=>7,'playingTime'=>30,'complexity'=>2.34,'categories'=>['Ancient','Card Game'],'mechanics'=>['Card Drafting','Set Collection'],'yearPublished'=>2010,'ratingBgg'=>7.73],
            ['bggId'=>'174430','name'=>'Gloomhaven','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>120,'complexity'=>3.86,'categories'=>['Adventure','Dungeon Crawl','Fantasy'],'mechanics'=>['Cooperative','Hand Management','Modular Board'],'yearPublished'=>2017,'ratingBgg'=>8.54],
            ['bggId'=>'167791','name'=>'Terraforming Mars','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>120,'complexity'=>3.24,'categories'=>['Economic','Science Fiction'],'mechanics'=>['Card Drafting','Hand Management','Tile Placement'],'yearPublished'=>2016,'ratingBgg'=>8.42],
            ['bggId'=>'162886','name'=>'Spirit Island','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>120,'complexity'=>3.89,'categories'=>['Fantasy','Cooperative'],'mechanics'=>['Cooperative','Hand Management','Area Control'],'yearPublished'=>2017,'ratingBgg'=>8.37],
            ['bggId'=>'266192','name'=>'Wingspan','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>70,'complexity'=>2.45,'categories'=>['Animals','Card Game'],'mechanics'=>['Card Drafting','Engine Building','Set Collection'],'yearPublished'=>2019,'ratingBgg'=>8.09],
            ['bggId'=>'230802','name'=>'Azul','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>45,'complexity'=>1.78,'categories'=>['Abstract','Pattern Building'],'mechanics'=>['Pattern Building','Tile Placement','Draft'],'yearPublished'=>2017,'ratingBgg'=>7.84],
            ['bggId'=>'224517','name'=>'Brass: Birmingham','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>120,'complexity'=>3.91,'categories'=>['Economic','Industry'],'mechanics'=>['Hand Management','Network Building','Route Building'],'yearPublished'=>2018,'ratingBgg'=>8.66],
            ['bggId'=>'169786','name'=>'Scythe','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>115,'complexity'=>3.41,'categories'=>['Economic','Wargame','Science Fiction'],'mechanics'=>['Area Control','Engine Building','Variable Powers'],'yearPublished'=>2016,'ratingBgg'=>8.24],
            ['bggId'=>'316554','name'=>'Dune: Imperium','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>120,'complexity'=>3.02,'categories'=>['Science Fiction','Worker Placement'],'mechanics'=>['Deck Building','Worker Placement','Area Control'],'yearPublished'=>2020,'ratingBgg'=>8.23],
            ['bggId'=>'237182','name'=>'Root','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>90,'complexity'=>3.72,'categories'=>['Adventure','Animals','Asymmetric'],'mechanics'=>['Area Control','Variable Asymmetric Powers'],'yearPublished'=>2018,'ratingBgg'=>8.13],
            ['bggId'=>'199792','name'=>'Everdell','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>80,'complexity'=>2.79,'categories'=>['Animals','Fantasy','Card Game'],'mechanics'=>['Card Drafting','Worker Placement','Hand Management'],'yearPublished'=>2018,'ratingBgg'=>8.07],
            ['bggId'=>'295947','name'=>'Cascadia','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>45,'complexity'=>1.83,'categories'=>['Animals','Nature'],'mechanics'=>['Tile Placement','Pattern Building','Draft'],'yearPublished'=>2021,'ratingBgg'=>7.94],
            ['bggId'=>'244521','name'=>'The Quacks of Quedlinburg','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>45,'complexity'=>1.89,'categories'=>['Humor','Bag Building'],'mechanics'=>['Bag Building','Push Your Luck'],'yearPublished'=>2018,'ratingBgg'=>7.87],
            ['bggId'=>'163412','name'=>'Patchwork','minPlayers'=>2,'maxPlayers'=>2,'playingTime'=>30,'complexity'=>1.67,'categories'=>['Abstract','Puzzle'],'mechanics'=>['Tile Placement','Pattern Building'],'yearPublished'=>2014,'ratingBgg'=>7.74],
            ['bggId'=>'128621','name'=>'Viticulture: Essential Edition','minPlayers'=>1,'maxPlayers'=>6,'playingTime'=>90,'complexity'=>2.87,'categories'=>['Economic','Worker Placement'],'mechanics'=>['Worker Placement','Hand Management','Set Collection'],'yearPublished'=>2015,'ratingBgg'=>8.07],
            ['bggId'=>'31260','name'=>'Agricola','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>120,'complexity'=>3.64,'categories'=>['Farming','Economic'],'mechanics'=>['Worker Placement','Hand Management'],'yearPublished'=>2007,'ratingBgg'=>7.94],
            ['bggId'=>'178900','name'=>'Codenames','minPlayers'=>2,'maxPlayers'=>8,'playingTime'=>15,'complexity'=>1.27,'categories'=>['Word Game','Party'],'mechanics'=>['Cooperative','Team-Based'],'yearPublished'=>2015,'ratingBgg'=>7.64],
            ['bggId'=>'39856','name'=>'Dixit','minPlayers'=>3,'maxPlayers'=>6,'playingTime'=>30,'complexity'=>1.12,'categories'=>['Party','Card Game','Art'],'mechanics'=>['Story Telling','Voting'],'yearPublished'=>2008,'ratingBgg'=>7.23],
            ['bggId'=>'181304','name'=>'Mysterium','minPlayers'=>2,'maxPlayers'=>7,'playingTime'=>42,'complexity'=>1.88,'categories'=>['Deduction','Horror','Cooperative'],'mechanics'=>['Cooperative','Voting','Pattern Recognition'],'yearPublished'=>2015,'ratingBgg'=>7.21],
            ['bggId'=>'54043','name'=>'Jaipur','minPlayers'=>2,'maxPlayers'=>2,'playingTime'=>30,'complexity'=>1.61,'categories'=>['Card Game','Economic'],'mechanics'=>['Hand Management','Set Collection','Trading'],'yearPublished'=>2009,'ratingBgg'=>7.71],
            ['bggId'=>'2651','name'=>'Power Grid','minPlayers'=>2,'maxPlayers'=>6,'playingTime'=>120,'complexity'=>3.28,'categories'=>['Economic','Negotiation'],'mechanics'=>['Auction','Route Building','Network Building'],'yearPublished'=>2004,'ratingBgg'=>7.81],
            ['bggId'=>'3076','name'=>'Puerto Rico','minPlayers'=>2,'maxPlayers'=>5,'playingTime'=>90,'complexity'=>3.27,'categories'=>['Economic','Colonization'],'mechanics'=>['Variable Phase Order','Hand Management'],'yearPublished'=>2002,'ratingBgg'=>8.02],
            ['bggId'=>'28143','name'=>'Race for the Galaxy','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>30,'complexity'=>2.98,'categories'=>['Card Game','Science Fiction'],'mechanics'=>['Card Drafting','Hand Management','Simultaneous Action'],'yearPublished'=>2007,'ratingBgg'=>7.80],
            ['bggId'=>'148228','name'=>'Splendor','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>30,'complexity'=>1.78,'categories'=>['Card Game','Economic'],'mechanics'=>['Set Collection','Engine Building'],'yearPublished'=>2014,'ratingBgg'=>7.44],
            ['bggId'=>'131357','name'=>'Coup','minPlayers'=>2,'maxPlayers'=>6,'playingTime'=>15,'complexity'=>1.36,'categories'=>['Bluffing','Card Game'],'mechanics'=>['Bluffing','Deduction','Player Elimination'],'yearPublished'=>2012,'ratingBgg'=>7.30],
            ['bggId'=>'98778','name'=>'Hanabi','minPlayers'=>2,'maxPlayers'=>5,'playingTime'=>25,'complexity'=>1.77,'categories'=>['Card Game','Cooperative'],'mechanics'=>['Cooperative','Hand Management','Memory'],'yearPublished'=>2010,'ratingBgg'=>7.01],
            ['bggId'=>'133473','name'=>'Sushi Go!','minPlayers'=>2,'maxPlayers'=>5,'playingTime'=>15,'complexity'=>1.03,'categories'=>['Card Game','Family'],'mechanics'=>['Card Drafting','Set Collection'],'yearPublished'=>2013,'ratingBgg'=>7.30],
            ['bggId'=>'129622','name'=>'Love Letter','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>20,'complexity'=>1.10,'categories'=>['Card Game','Deduction'],'mechanics'=>['Hand Management','Player Elimination','Deduction'],'yearPublished'=>2012,'ratingBgg'=>7.11],
            ['bggId'=>'70323','name'=>'King of Tokyo','minPlayers'=>2,'maxPlayers'=>6,'playingTime'=>30,'complexity'=>1.49,'categories'=>['Dice Game','Monsters'],'mechanics'=>['Dice Rolling','Push Your Luck','Player Elimination'],'yearPublished'=>2011,'ratingBgg'=>7.15],
            ['bggId'=>'150376','name'=>'Dead of Winter','minPlayers'=>2,'maxPlayers'=>5,'playingTime'=>100,'complexity'=>2.96,'categories'=>['Horror','Zombie','Semi-Cooperative'],'mechanics'=>['Cooperative','Dice Rolling','Hand Management'],'yearPublished'=>2014,'ratingBgg'=>7.48],
            ['bggId'=>'170216','name'=>'Blood Rage','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>90,'complexity'=>2.89,'categories'=>['Fantasy','Wargame','Area Control'],'mechanics'=>['Card Drafting','Area Control','Variable Powers'],'yearPublished'=>2015,'ratingBgg'=>7.91],
            ['bggId'=>'201808','name'=>'Clank!','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>60,'complexity'=>2.24,'categories'=>['Adventure','Deck Building','Fantasy'],'mechanics'=>['Deck Building','Push Your Luck','Movement'],'yearPublished'=>2016,'ratingBgg'=>7.52],
            ['bggId'=>'204583','name'=>'Kingdomino','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>15,'complexity'=>1.22,'categories'=>['Abstract','Family'],'mechanics'=>['Tile Placement','Pattern Building','Draft'],'yearPublished'=>2016,'ratingBgg'=>7.30],
            ['bggId'=>'121921','name'=>'Robinson Crusoe','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>90,'complexity'=>3.50,'categories'=>['Adventure','Survival','Cooperative'],'mechanics'=>['Cooperative','Dice Rolling','Hand Management'],'yearPublished'=>2012,'ratingBgg'=>7.77],
            ['bggId'=>'35677','name'=>'Le Havre','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>120,'complexity'=>3.69,'categories'=>['Economic','Industry'],'mechanics'=>['Hand Management','Worker Placement','Resource Management'],'yearPublished'=>2008,'ratingBgg'=>7.88],
            ['bggId'=>'236457','name'=>'Architects of the West Kingdom','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>80,'complexity'=>2.70,'categories'=>['Economic','Worker Placement','Medieval'],'mechanics'=>['Worker Placement','Area Control','Set Collection'],'yearPublished'=>2018,'ratingBgg'=>7.73],
            ['bggId'=>'173090','name'=>'The Game','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>20,'complexity'=>1.57,'categories'=>['Card Game','Cooperative'],'mechanics'=>['Cooperative','Hand Management'],'yearPublished'=>2015,'ratingBgg'=>7.01],
            ['bggId'=>'15987','name'=>'Arkham Horror (2nd Ed)','minPlayers'=>1,'maxPlayers'=>8,'playingTime'=>180,'complexity'=>3.60,'categories'=>['Adventure','Horror','Cooperative'],'mechanics'=>['Cooperative','Dice Rolling','Hand Management'],'yearPublished'=>2005,'ratingBgg'=>7.22],
            ['bggId'=>'63268','name'=>'Spot It!','minPlayers'=>2,'maxPlayers'=>8,'playingTime'=>15,'complexity'=>1.04,'categories'=>['Card Game','Family','Party'],'mechanics'=>['Pattern Recognition','Real-Time'],'yearPublished'=>2009,'ratingBgg'=>6.72],
            ['bggId'=>'65244','name'=>'Forbidden Island','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>30,'complexity'=>1.71,'categories'=>['Adventure','Cooperative'],'mechanics'=>['Cooperative','Hand Management','Movement'],'yearPublished'=>2010,'ratingBgg'=>6.84],
            ['bggId'=>'157969','name'=>'Sheriff of Nottingham','minPlayers'=>3,'maxPlayers'=>5,'playingTime'=>60,'complexity'=>1.91,'categories'=>['Bluffing','Negotiation'],'mechanics'=>['Bluffing','Trading','Negotiation'],'yearPublished'=>2014,'ratingBgg'=>7.04],
            ['bggId'=>'37111','name'=>'Battlestar Galactica','minPlayers'=>3,'maxPlayers'=>6,'playingTime'=>180,'complexity'=>3.15,'categories'=>['Bluffing','Science Fiction','Semi-Cooperative'],'mechanics'=>['Cooperative','Hand Management','Voting'],'yearPublished'=>2008,'ratingBgg'=>7.68],
            ['bggId'=>'246570','name'=>'Dice Throne','minPlayers'=>2,'maxPlayers'=>6,'playingTime'=>30,'complexity'=>1.78,'categories'=>['Dice Game','Fighting'],'mechanics'=>['Dice Rolling','Push Your Luck','Variable Powers'],'yearPublished'=>2018,'ratingBgg'=>7.72],
            ['bggId'=>'172225','name'=>'Exploding Kittens','minPlayers'=>2,'maxPlayers'=>5,'playingTime'=>15,'complexity'=>1.04,'categories'=>['Card Game','Humor','Party'],'mechanics'=>['Hand Management','Player Elimination','Push Your Luck'],'yearPublished'=>2015,'ratingBgg'=>6.10],
            ['bggId'=>'314499','name'=>'Meadow','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>60,'complexity'=>1.93,'categories'=>['Animals','Nature','Card Game'],'mechanics'=>['Card Drafting','Set Collection','Pattern Building'],'yearPublished'=>2021,'ratingBgg'=>7.31],
            ['bggId'=>'256916','name'=>'Wingspan: European Expansion','minPlayers'=>1,'maxPlayers'=>5,'playingTime'=>70,'complexity'=>2.42,'categories'=>['Animals','Card Game'],'mechanics'=>['Card Drafting','Engine Building','Set Collection'],'yearPublished'=>2019,'ratingBgg'=>8.00],
            ['bggId'=>'396790','name'=>'Sky Team','minPlayers'=>2,'maxPlayers'=>2,'playingTime'=>20,'complexity'=>2.16,'categories'=>['Cooperative','Dice Game'],'mechanics'=>['Cooperative','Dice Rolling','Real-Time'],'yearPublished'=>2023,'ratingBgg'=>8.16],
            ['bggId'=>'291453','name'=>'Ark Nova','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>150,'complexity'=>3.72,'categories'=>['Animals','Economic'],'mechanics'=>['Card Drafting','Hand Management','Worker Placement'],'yearPublished'=>2021,'ratingBgg'=>8.58],
            ['bggId'=>'269385','name'=>'Paleo','minPlayers'=>2,'maxPlayers'=>4,'playingTime'=>60,'complexity'=>2.52,'categories'=>['Cooperative','Adventure'],'mechanics'=>['Cooperative','Hand Management','Modular Board'],'yearPublished'=>2020,'ratingBgg'=>7.76],
            ['bggId'=>'322949','name'=>'Heat: Pedal to the Metal','minPlayers'=>1,'maxPlayers'=>6,'playingTime'=>60,'complexity'=>2.01,'categories'=>['Racing','Card Game'],'mechanics'=>['Hand Management','Racing','Card Drafting'],'yearPublished'=>2022,'ratingBgg'=>7.82],
            ['bggId'=>'255984','name'=>'Cryptid','minPlayers'=>3,'maxPlayers'=>5,'playingTime'=>50,'complexity'=>2.17,'categories'=>['Deduction','Puzzle'],'mechanics'=>['Deduction','Logical Deduction'],'yearPublished'=>2018,'ratingBgg'=>7.52],
            ['bggId'=>'220308','name'=>'Ganz Schön Clever','minPlayers'=>1,'maxPlayers'=>4,'playingTime'=>30,'complexity'=>1.88,'categories'=>['Dice Game','Abstract'],'mechanics'=>['Dice Rolling','Push Your Luck','Paper & Pen'],'yearPublished'=>2018,'ratingBgg'=>7.64],
        ];
    }
}
