<?php
	include("config.php");

	class CookbookDB extends DB {
		const RECIPE		= "recipe";
		const TYPE			= "type";
		const INGREDIENT	= "ingredient";
		const CATEGORY		= "ingredient_category";
		const USER			= "user";
		const AMOUNT		= "amount";
		
		private $image_path = "";

		public function __construct($image_path) {
			global $user, $pass, $db;
			$this->image_path = $image_path;
			parent::__construct("mysql:host=localhost;dbname=".$db, $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		}
		
		public function validateLogin($login, $password) {
			$hash = sha1($password);
			$data = $this->query("SELECT id, name FROM ".self::USER." WHERE login = ? AND pwd = ?", $login, $password);
			return (count($data) ? $data[0] : null);
		}

		public function getRecipes() {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." ORDER by name ASC");
			return $this->addImageInfo($data);
		}

		public function getTypes() {
			return $this->query("SELECT id, name FROM ".self::TYPE." ORDER by `order` ASC");
		}

		public function getIngredients() {
			$ingredients = $this->query("SELECT * FROM ".self::INGREDIENT." ORDER by name ASC");
			$categories = $this->getCategories();
			
			$tmp = array(); /* temporary id-indexed categories */
			for ($i=0;$i<count($categories);$i++) {
				$id = $categories[$i]["id"];
				$tmp[$id] = $categories[$i];
				$tmp[$id]["ingredients"] = array();
			}
			
			for ($i=0;$i<count($ingredients);$i++) {
				$id = $ingredients[$i]["id_category"];
				$tmp[$id]["ingredients"][] = $ingredients[$i];
			}
			
			$result = array();
			foreach ($tmp as $item) { $result[] = $item; }
			
			return $result;
		}
		
		public function getUsers() {
			return $this->query("SELECT id, name FROM ".self::USER." ORDER by id ASC");
		}
		
		private function getCategories() {
			return $this->query("SELECT id, name FROM ".self::CATEGORY." ORDER by `order` ASC");
		}

		/***/

		public function getRecipe($id) {
			$data = $this->query("SELECT * FROM ".self::RECIPE." WHERE id = ?", $id);
			if (!count($data)) { return null; }
			$data = $this->addImageInfo($data);
			
			$data = $data[0];
			$data["text"] = array(""=>$data["text"]);
			$data["remark"] = array(""=>$data["remark"]);
			
			$data["ingredient"] = $this->getAmounts($id);

			return $data; 
		}

		public function getType($id) {
			return $this->query("SELECT * FROM ".self::TYPE." WHERE id = ?", $id);
		}

		public function getIngredient($id) {
			return $this->query("SELECT * FROM ".self::INGREDIENT." WHERE id = ?", $id);
		}

		public function getCategory($id) {
			return $this->query("SELECT * FROM ".self::CATEGORY." WHERE id = ?", $id);
		}

		public function getUser($id) {
			return $this->query("SELECT * FROM ".self::USER." WHERE id = ?", $id);
		}
		
		public function getAmounts($id_recipe) {
			return $this->query("SELECT ".self::INGREDIENT.".name, ".self::AMOUNT.".amount
									FROM ".self::AMOUNT."
									LEFT JOIN ".self::INGREDIENT." ON ".self::AMOUNT.".id_ingredient = ".self::INGREDIENT.".id
									LEFT JOIN ".self::CATEGORY." ON ".self::INGREDIENT.".id_category = ".self::CATEGORY.".id
									WHERE ".self::AMOUNT.".id_recipe = ?
									ORDER BY ".self::CATEGORY.".`order` ASC, ".self::INGREDIENT.".name ASC", $id_recipe);
		}

		/***/
		
		public function getLatestRecipes($amount = 10) {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." ORDER BY ts DESC LIMIT ". (int) $amount);
			return $this->addImageInfo($data);
		}
		
		public function getRandomRecipes($id_types, $amount = 10) {
			$data = $this->query("SELECT id, name 
									FROM ".self::RECIPE."
									WHERE id_type IN (".implode(",",$id_types).")
									LIMIT ".(int)$amount." 
									ORDER BY name ASC");
			return $this->addImageInfo($data);
		}
		
		public function searchRecipes($query) {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." WHERE name LIKE ?", "%".$query."%");
			return $this->addImageInfo($data);
		}
		
		public function getRecipesForType($id_type) {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." WHERE id_type = ? ORDER BY name ASC", $id_type);
			return $this->addImageInfo($data);
		}
		
		public function getRecipesForUser($id_user) {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." WHERE id_user = ? ORDER BY name ASC", $id_user);
			return $this->addImageInfo($data);
		}

		public function getRecipesForIngredient($id_ingredient) {
			/* FIXME obohatit, obrazek */
			return $this->query("SELECT DISTINCT id_recipe FROM ".self::AMOUNT." WHERE id_ingredient = ? ORDER BY id_recipe ASC", $id_ingredient);
		}

		public function getIngredientsForCategory($id_category) {
			return $this->query("SELECT id, name FROM ".self::INGREDIENT." WHERE id_category = ? ORDER BY name ASC", $id_category);
		}

		private function addImageInfo($recipes) {
			for ($i=0;$i<count($recipes);$i++) {
				$recipes[$i]["image"] = (file_exists($this->image_path . "/" . $recipes[$i]["id"] . ".jpg") ? 1 : 0);
			}
			return $recipes;
		}
		
		/***/
		
		public function deleteRecipe($id) {
			$this->delete(self::RECIPE, $id);
			$this->delete(self::AMOUNT, array("id_recipe" => $id));
			return true;
		}

		public function deleteType($id) {
			if (count($this->getRecipesForType($id))) { return false; }
			$this->delete(self::TYPE, $id);
			return true;
		}

		public function deleteIngredient($id) {
			if (count($this->getRecipesForIngredient($id))) { return false; }
			$this->delete(self::INGREDIENT, $id);
			return true;
		}

		public function deleteCategory($id) {
			if (count($this->getIngredientsForCategory($id))) { return false; }
			$this->delete(self::CATEGORY, $id);
			return true;
		}

		public function deleteUser($id) {
			if (count($this->getRecipesForUser($id))) { return false; }
			$this->delete(self::USER, $id);
			return true;
		}
	}

?>
