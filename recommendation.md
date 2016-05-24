# Recommendation code

## Rendre le code conforme à PSR-4 et aux PSR en général (1, 2 et 3)

L’écosystème php est vaste, beaucoup d’outils, framework, cms sont disponibles. 
Avec autant d’acteurs à prendre en compte il est important de suivre certaines « normes » afin d’homogénéiser les développement, 
faciliter l’interaction des composant mis a disposition par différentes sources et simplifier la maintenabilité du code.

La norme PSR-4 permet d’avoir a disposition un « autoloading » avancé, elle complète PSR-0. 
« L’autoload » composer est compatible avec cette norme.

La norme PSR-3 défini des interfaces pour les log. Monolog par exemple répond à cette norme

Les norme PSR-1 et PSR-2 définissent les règles de codage et le style pour l’écriture de code.

[Norme PSR-4](http://www.php-fig.org/psr/psr-4/)


## Utiliser un « framework » pour l’accès aux données 

Permet de normaliser les « requêtage » d’accès aux données en plus d’avoir une abstraction de la base de données. 
De plus ils peuvent permettre  d’avoir un « mapping » entre le relationnel et les entités objets.

Exemple de code avec un orm :

```php
<?php
$subscription = $entityManager->getRepository('BillingSubscription')->find(2);
echo $subscription->getId(); //2
echo $subscription->getProvider()->getName(); // recurly
$subscription->setSubUuid('my uuid'); //change uuid

$entityManager->persist($subscription); // ask to persists this entity
$entityManager->persist($otherObject); // ask to persists this entity
$entityManager->flush(); //  commit changes in databases
```


Les plus en vues pour postgres : Doctrine2 qui est un orm et Pomm qui n'en est pas un

[doctrine2](http://www.doctrine-project.org/)
[Pomm](http://www.pomm-project.org/)


## Utiliser un container d’injection de dépendance

Permet d’éviter d’avoir un couplage fort entre les différents objets. Le container se charge de fabriquer les objets et les dépendances requises.

[Pimple](http://pimple.sensiolabs.org)
[php-di](http://php-di.org)

## Utiliser un framework fullstack 

Un framework fullstack moderne comme symfony2(inpiré de spring) prend en charge toutes les choses ci-dessus.
Un framework apporte un cadre de développement précis en apportant une organisation du projet : 
(découpage logique du code source, factorisation de code, maintenance et évolution du projet facilité).
Toute nouvelle personne connaissant le framework pourra prendre en main le projet facilement.

Le revers de la médaille étant une courbe d'apprentissage un peu plus difficile au départ.

Les frameworks les plus utilisés sont :

[Symfony2](http://symfony.com)
[Zend](http://framework.zend.com)