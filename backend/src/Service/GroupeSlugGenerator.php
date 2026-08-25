<?php
namespace App\Service;
use App\Repository\GroupeRepository;
final class GroupeSlugGenerator { public function __construct(private readonly GroupeRepository $groups){} public function generate(string $name):string{$base=trim((string)preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/','-',strtolower(iconv('UTF-8','ASCII//TRANSLIT',$name) ?: $name))));$base=trim($base,'-') ?: 'faction';$slug=$base;for($i=2;$this->groups->findOneBy(['slug'=>$slug])!==null;$i++)$slug=$base.'-'.$i;return $slug;}}
