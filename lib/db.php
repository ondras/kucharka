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
			global $user, $pass, $host, $db;
			$this->image_path = $image_path;
			parent::__construct("mysql:host=".$host.";dbname=".$db, $user, $pass, array(Pdo\Mysql::ATTR_INIT_COMMAND => "SET NAMES utf8"));
		}

		public function getFields($table) {
			switch ($table) {
				case self::RECIPE: return array("name", "id_type", "time", "amount", "text", "remark", "hot_tip"); break;
				case self::TYPE: return array("name"); break;
				case self::INGREDIENT: return array("name", "id_category", "description"); break;
				case self::CATEGORY: return array("name"); break;
				case self::USER: return array("name", "mail"); break;
				default: return array();
			}

		}

		public function getImagePath($table) {
			switch ($table) {
				case self::RECIPE: return "recipes"; break;
				case self::INGREDIENT: return "ingredients"; break;
				case self::USER: return "users"; break;

				case self::TYPE:
				case self::CATEGORY:
				default: null;
			}

		}

		public function validateLogin($mail, $password) {
			$hash = sha1($password);
			$data = $this->query("SELECT id, name, super FROM ".self::USER." WHERE mail = ? AND pwd = ?", $mail, $hash);
			return (count($data) ? $data[0] : null);
		}

		public function getRecipeCount() {
			$data = $this->query("SELECT COUNT(id) AS c FROM ".self::RECIPE);
			return $data[0]["c"];
		}

		public function getRecipes() {
			$data = $this->query("SELECT id, name, id_type FROM ".self::RECIPE." ORDER by name ASC");
			return $this->addImageInfo($data, self::RECIPE);
		}

		public function getTypes($withRecipes) {
			$types = $this->query("SELECT ".self::TYPE.".id, ".self::TYPE.".name, COUNT(".self::RECIPE.".id) AS count
									FROM ".self::TYPE."
									LEFT JOIN ".self::RECIPE." ON ".self::RECIPE.".id_type = ".self::TYPE.".id
									GROUP BY ".self::TYPE.".id
									ORDER by `order` ASC");
			if (!$withRecipes) { return $types; }

			$recipes = $this->getRecipes();

			$tmp = array(); /* temporary id-indexed types */
			for ($i=0;$i<count($types);$i++) {
				$id = $types[$i]["id"];
				$tmp[$id] = $types[$i];
				$tmp[$id]["recipe"] = array();
			}

			for ($i=0;$i<count($recipes);$i++) {
				$id = $recipes[$i]["id_type"];
				$tmp[$id]["recipe"][] = $recipes[$i];
			}

			$result = array();
			foreach ($tmp as $item) { $result[] = $item; }

			return $result;
		}

		public function getIngredients() {
			$ingredients = $this->query("SELECT id, name, id_category FROM ".self::INGREDIENT." ORDER by name ASC");
			$ingredients = $this->addImageInfo($ingredients, self::INGREDIENT);

			$categories = $this->getCategories();

			$tmp = array(); /* temporary id-indexed categories */
			for ($i=0;$i<count($categories);$i++) {
				$id = $categories[$i]["id"];
				$tmp[$id] = $categories[$i];
				$tmp[$id]["ingredient"] = array();
			}

			for ($i=0;$i<count($ingredients);$i++) {
				$id = $ingredients[$i]["id_category"];
				$tmp[$id]["ingredient"][] = $ingredients[$i];
			}

			$result = array();
			foreach ($tmp as $item) { $result[] = $item; }

			return $result;
		}

		public function getUsers() {
			$data = $this->query("SELECT ".self::USER.".id, ".self::USER.".name, COUNT(".self::RECIPE.".id) AS recipes
								FROM ".self::USER."
								LEFT JOIN ".self::RECIPE." ON ".self::RECIPE.".id_user = ".self::USER.".id
								GROUP BY ".self::USER.".id
								ORDER BY ".self::USER.".id ASC");
			return $this->addImageInfo($data, self::USER);
		}

		public function getCategories() {
			return $this->query("SELECT id, name FROM ".self::CATEGORY." ORDER by `order` ASC");
		}

		/***/

		public function getHotTipId() {
			$data = $this->query("SELECT id FROM ".self::RECIPE." WHERE hot_tip = ?", 1);
			return (count($data) ? $data[0]["id"] : null);
		}

		public function getRecipe($id) {
			$data = $this->query("SELECT ".self::RECIPE.".*,
								YEAR(ts) AS year, MONTH(ts) AS month, DAY(ts) AS day,
								".self::USER.".name AS name_user,
								".self::TYPE.".name AS name_type
								FROM ".self::RECIPE."
								LEFT JOIN ".self::USER." ON ".self::RECIPE.".id_user = ".self::USER.".id
								LEFT JOIN ".self::TYPE." ON ".self::RECIPE.".id_type = ".self::TYPE.".id
								WHERE ".self::RECIPE.".id = ?", $id);
			if (!count($data)) { return null; }
			$data = $this->addImageInfo($data, self::RECIPE);

			$data = $data[0];
			$data["text"] = array(""=>$data["text"]);
			$data["remark"] = array(""=>$data["remark"]);

			$amounts = $this->getAmounts($id);
			if (count($amounts)) { $data["ingredient"] = $amounts; }

			return $data;
		}

		public function getType($id) {
			$data = $this->query("SELECT * FROM ".self::TYPE." WHERE id = ?", $id);
			if (!count($data)) { return null; }
			return $data[0];
		}

		public function getIngredient($id) {
			$data = $this->query("SELECT * FROM ".self::INGREDIENT." WHERE id = ?", $id);
			if (!count($data)) { return null; }
			$data = $this->addImageInfo($data, self::INGREDIENT);
			return $data[0];
		}

		public function getCategory($id) {
			$data = $this->query("SELECT * FROM ".self::CATEGORY." WHERE id = ?", $id);
			if (!count($data)) { return null; }
			return $data[0];
		}

		public function getUser($id) {
			$data = $this->query("SELECT id, super, name, mail FROM ".self::USER." WHERE id = ?", $id);
			if (!count($data)) { return null; }
			$data = $this->addImageInfo($data, self::USER);
			return $data[0];
		}

		public function getAmounts($id_recipe) {
			return $this->query("SELECT ".self::INGREDIENT.".name, ".self::AMOUNT.".amount, ".self::AMOUNT.".id_ingredient
									FROM ".self::AMOUNT."
									LEFT JOIN ".self::INGREDIENT." ON ".self::AMOUNT.".id_ingredient = ".self::INGREDIENT.".id
									LEFT JOIN ".self::CATEGORY." ON ".self::INGREDIENT.".id_category = ".self::CATEGORY.".id
									WHERE ".self::AMOUNT.".id_recipe = ?
									ORDER BY ".self::CATEGORY.".`order` ASC, ".self::INGREDIENT.".name ASC", $id_recipe);
		}

		/***/

		public function getLatestRecipes($amount = 10) {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." ORDER BY ts DESC LIMIT ". (int) $amount);
			return $this->addImageInfo($data, self::RECIPE);
		}

		public function getRandomRecipes($id_types, $count = 10) {
			$data = $this->query("SELECT id, name
									FROM ".self::RECIPE."
									WHERE id_type IN (".implode(",",$id_types).")
									ORDER BY RAND() ASC
									LIMIT ".(int)$count);
			return $this->addImageInfo($data, self::RECIPE);
		}

		public function searchRecipes($conditions) {
			$c = array("1");
			$params = array();

			if (array_key_exists("query", $conditions)) {
				$c[] = "name COLLATE utf8_general_ci LIKE ?";
				$params[] = "%".$conditions["query"]."%";
			}

			if (array_key_exists("id_type", $conditions)) {
				$c[] = "id_type = ?";
				$params[] = $conditions["id_type"];
			}

			if (array_key_exists("time", $conditions)) {
				$t = $conditions["time"];
				$time_type = $t[0];
				$time = $t[1];
				$c[] = "time ". ($time_type == 1 ? "<=" : ">=") . " ?";
				$params[] = $time;
			}

			if (array_key_exists("amount", $conditions)) {
				$c[] = "amount COLLATE utf8_general_ci LIKE ?";
				$params[] = "%".$conditions["amount"]."%";
			}

			if (array_key_exists("id_ingredient", $conditions)) {
				$c[] = "id IN (
							SELECT DISTINCT id_recipe
							FROM ".self::AMOUNT."
							WHERE id_ingredient = ?
						)";
				$params[] = $conditions["id_ingredient"];
			}

			if (array_key_exists("id_user", $conditions)) {
				$c[] = "id_user = ?";
				$params[] = $conditions["id_user"];
			}

			$data = $this->query("SELECT id, name
									FROM ".self::RECIPE."
									WHERE ".implode(" AND ", $c)."
									ORDER BY name ASC", $params);
			return $this->addImageInfo($data, self::RECIPE);
		}

		public function getRecipesForType($id_type) {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." WHERE id_type = ? ORDER BY name ASC", $id_type);
			return $this->addImageInfo($data, self::RECIPE);
		}

		public function getRecipesForUser($id_user) {
			$data = $this->query("SELECT id, name FROM ".self::RECIPE." WHERE id_user = ? ORDER BY name ASC", $id_user);
			return $this->addImageInfo($data, self::RECIPE);
		}

		public function getRecipesForIngredient($id_ingredient) {
			$data = $this->query("SELECT
									DISTINCT id_recipe AS id,
									".self::RECIPE.".name AS name
									FROM ".self::AMOUNT."
									LEFT JOIN ".self::RECIPE." ON ".self::AMOUNT.".id_recipe = ".self::RECIPE.".id
									WHERE id_ingredient = ?
									ORDER BY id_recipe ASC", $id_ingredient);
			return $this->addImageInfo($data, self::RECIPE);
		}

		public function getIngredientsForCategory($id_category) {
			return $this->query("SELECT id, name FROM ".self::INGREDIENT." WHERE id_category = ? ORDER BY name ASC", $id_category);
		}

		public function getUserForMail($mail) {
			$data = $this->query("SELECT * FROM ".self::USER." WHERE mail = ?", $mail);
			if (!count($data)) { return null; }
			$data = $this->addImageInfo($data, self::USER);
			return $data[0];
		}

		public function getSimilarRecipes($id) {
			$recipe = $this->getRecipe($id);
			if (!$recipe) { return array(); }

			$ingredients = (array_key_exists("ingredient", $recipe) ? count($recipe["ingredient"]) : 0);
			$similar = $this->query("SELECT ".self::RECIPE.".id, ".self::RECIPE.".name,
										2*SUM( IF (id_ingredient IN (
											SELECT id_ingredient
											FROM ".self::AMOUNT."
											WHERE id_recipe = ?
										),1,0) ) -
										1*SUM( IF (id_ingredient NOT IN (
											SELECT id_ingredient
											FROM ".self::AMOUNT."
											WHERE id_recipe =  ?
										),1,0) )
										- ? AS relevance
										FROM ".self::AMOUNT."
										LEFT JOIN ".self::RECIPE." ON ".self::AMOUNT.".id_recipe = ".self::RECIPE.".id
										WHERE id_recipe <> ? AND id_type = ?
										GROUP BY id_recipe
										HAVING relevance > 0
										ORDER BY relevance DESC
										LIMIT 5", $id, $id, $ingredients, $id, $recipe["id_type"]);
			return $this->addImageInfo($similar, self::RECIPE);
		}

		private function addImageInfo($recipes, $table) {
			$path = $this->getImagePath($table);

			for ($i=0;$i<count($recipes);$i++) {
				$exists = file_exists($this->image_path . "/" . $path . "/" . $recipes[$i]["id"] . ".jpg");
				$recipes[$i]["image"] = ($exists ? 1 : 0);
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

		/***/

		public function insertType() {
			$data = $this->query("SELECT MAX(`order`) AS m FROM ".self::TYPE);
			$order = $data[0]["m"] + 1;
			return $this->insert(self::TYPE, array("`order`"=>$order));
		}

		public function insertCategory() {
			$data = $this->query("SELECT MAX(`order`) AS m FROM ".self::CATEGORY);
			$order = $data[0]["m"] + 1;
			return $this->insert(self::CATEGORY, array("`order`"=>$order));
		}

		public function insertRecipe($id_user) {
			return $this->insert(self::RECIPE, array("id_user"=>$id_user));
		}

		public function updateUser($id, $values, $password = null) {
			if ($password) { $values["pwd"] = sha1($password); }
			return $this->update(self::USER, $id, $values);
		}

		public function updateRecipe($id, $values, $ingredients) {
			if ($values["hot_tip"] == 1) { $this->update(self::RECIPE, null, array("hot_tip"=>0)); }
			$this->update(self::RECIPE, $id, $values);
			$this->delete(self::AMOUNT, array("id_recipe"=>$id));

			foreach ($ingredients as $ingredient) {
				$ingredient["id_recipe"] = $id;
				$this->insert(self::AMOUNT, $ingredient);
			}
		}

		/**
		 * Move record up/down using its "order" column
		 * @param {string} table
		 * @param {int} id
		 * @param {int} direction +1 down, -1 up
		 */
		public function move($table, $id, $direction) {
			$order = $this->query("SELECT `order` FROM ".$table." WHERE id = ?", $id);
			if (!count($order)) { return false; }
			$order = $order[0]["order"];

			$operator = ($direction == 1 ? ">" : "<");
			$sibling = $this->query("SELECT id, `order` FROM ".$table." WHERE `order` ".$operator. " ? LIMIT 1", $order);
			if (!count($sibling)) { return false; }
			$sibling_id = $sibling[0]["id"];
			$sibling_order = $sibling[0]["order"];

			$this->update($table, $sibling_id, array("`order`"=>$order));
			$this->update($table, $id, array("`order`"=>$sibling_order));
			return true;
		}
	}
?>
