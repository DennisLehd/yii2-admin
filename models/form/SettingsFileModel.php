<?php

namespace mdm\admin\models\form;

use mdm\admin\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\Json;

class SettingsFileModel extends Model
{
    public $file;

    public function rules()
    {
        return [
            [['file'], 'file', 'skipOnEmpty' => false, 'extensions' => 'json'],
            /*
            [
                ['file'],
                'file',
                //'skipOnEmpty' => false,
                'extensions' => 'json',
                //'maxSize' => 1024 * 1024,
                //'tooBig' => Yii::t('rbac-admin', 'The file is too big. Maximum size is {size} bytes.', ['size' => 1024 * 1024]),
                //'wrongExtension' => Yii::t('rbac-admin', 'Only JSON files are allowed.'),
                //'checkExtensionByMimeType' => false,
                //'mimeTypes' => 'application/json',
            ],
            */
            [
                ['file'],
                'required',
                'message' => Yii::t('rbac-admin', 'Please select a file to import.')
            ],

        ];
    }

    /**
     * Validates the RBAC graph definitions.
     *
     * @param array $definitions The RBAC definitions to validate.
     * @return array An array containing 'errors' and 'warnings'.
     */
    function validateRbacGraph(array $definitions): array
    {
        $errors = [];
        $warnings = [];

        // Flatten names
        $allItems = array_merge(
            array_keys($definitions['roles'] ?? []),
            array_keys($definitions['permissions'] ?? []),
            array_keys($definitions['routes'] ?? [])
        );

        $validItems = array_flip($allItems);

        // --- Validate relations point to known items ---
        foreach ($definitions['relations'] ?? [] as $rel) {
            $parent = $rel['parent'];
            $child = $rel['child'];

            if (!isset($validItems[$parent])) {
                $errors[] = "Relation error: parent '$parent' is not defined in roles, permissions, or routes.";
            }
            if (!isset($validItems[$child])) {
                $errors[] = "Relation error: child '$child' is not defined in roles, permissions, or routes.";
            }
        }

        // --- Build graph for cycle detection ---
        $graph = [];
        foreach ($definitions['relations'] ?? [] as $rel) {
            $graph[$rel['parent']][] = $rel['child'];
        }

        // --- Detect cycles with DFS ---
        $visited = [];
        $stack = [];

        $dfs = function ($node) use (&$dfs, &$graph, &$visited, &$stack, &$errors) {
            if (isset($stack[$node])) {
                $cycle = implode(' → ', array_keys($stack)) . " → $node";
                $errors[] = "Cycle detected: $cycle";
                return;
            }
            if (isset($visited[$node])) {
                return;
            }

            $visited[$node] = true;
            $stack[$node] = true;

            foreach ($graph[$node] ?? [] as $child) {
                $dfs($child);
            }

            unset($stack[$node]);
        };

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $dfs($node);
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function importFile()
    {
        if ($this->validate()) {
            $json = file_get_contents($this->file->tempName);
            return $this->databaseImport(Json::decode($json));
        } else {         
            return false;
        }
    }

    /**
     * Exports the current RBAC definitions from the database.
     *
     * @param bool $asJson Whether to return the result as JSON string.
     * @return array|string The RBAC definitions array or JSON string.
     */
    public function databaseExport(bool $asJson = false)
    {
        $auth = Yii::$app->authManager;
        // Roles 
        $roles = $auth->getRoles();

        // Assignments
        $assignments = [];
        foreach (User::find()->all() as $user) {
            $userAssignments = $auth->getAssignments($user->id);
            foreach ($userAssignments as $assignment) {
                if (!isset($assignments[$user->username])) {
                    $assignments[$user->username] = [];
                }
                $assignments[$user->username][] = $assignment->roleName;
            }
        }

        // All rules
        $rules = [];
        foreach ($auth->getRules() as $name => $rule) {
            $rules[$name] = [
                'name' => $rule->name,
                'createdAt' => $rule->createdAt,
                'updatedAt' => $rule->updatedAt,
                'class' => get_class($rule), // ✅ ADD THIS
                // optionally include:
                // 'data' => base64_encode(serialize($rule)), // only if you want to restore full state
            ];
        }


        // Split permissions into routes and other permissions
        $routes = [];
        $permissions = [];
        $routesAndPermissions = $auth->getPermissions();
        foreach ($routesAndPermissions as $name => $perm) {
            if (str_starts_with($name, '/')) {
                // $alias = $this->routeAliasFromPath($name); // Optional helper to reverse map
                // $routes[$alias] = $name;
                $routes[$name] = $perm;
            } else {
                $permissions[$name] = $perm;
            }
        }

        // Get all relations between roles, permissions, and routes
        $parentChildRelations = $this->getAllAuthRelations($auth);

        $result = [
            'routes' => $routes,
            'permissions' => $permissions,
            'roles' => $roles,
            'assignments' => $assignments,
            'rules' => $rules,
            'relations' => $parentChildRelations
        ];

        if ($asJson) {
            return Json::encode($result);
        }
        return $result;
    }

    /**
     * Imports RBAC definitions from an array into the database.
     *
     * @param array $definitionsArray The RBAC definitions to import.
     * @return bool True on success, false on failure.
     */
    public function databaseImport(array $definitionsArray)
    {

        // Validate the definitions structure
        $validation = $this->validateRbacGraph($definitionsArray);
        if (!empty($validation['errors'])) {
            Yii::error("RBAC definitions validation failed: " . implode("\n", $validation['errors']), 'error');
            return false;
        }
        if (!empty($validation['warnings'])) {
            Yii::warning("RBAC definitions validation warnings: " . implode("\n", $validation['warnings']), 'warning');
        }

        // Start transaction to ensure atomicity
        $connection = Yii::$app->db->beginTransaction();
        $log = [];
        try {
            $auth = Yii::$app->authManager;

            // Remove all existing RBAC data
            $auth->removeAll();

            // Add rules            
            foreach ($definitionsArray['rules'] as $name => $rule) {
                $class = $rule['class'];
                if (class_exists($class)) {
                    $rule = new $class();
                    if ($rule instanceof \yii\rbac\Rule) {
                        $auth->add($rule);
                        $log[] = "Added rule: $name";
                    } else {
                        Yii::warning("Class '$class' is not a valid RBAC rule.");
                    }
                } else {
                    Yii::warning("Rule class '$class' does not exist.");
                }
            }
            $authModels = [];

            // Add routes, permissions, and roles
            foreach ($definitionsArray['routes'] as $alias => $route) {
                $perm = $auth->createPermission($route);
                $perm->name = $alias;
                $auth->add($perm);
                $authModels[$alias] = $perm;
                $log[] = "Added route permission: $alias";
            }

            foreach ($definitionsArray['permissions'] as $name => $perm) {
                $permission = $auth->createPermission($name);
                if (isset($perm['ruleName']) && !empty($perm['ruleName'])) {
                    $permission->ruleName = $perm['ruleName'];
                }
                $auth->add($permission);
                $authModels[$name] = $permission;
                $log[] = "Added permission: $name";
            }

            foreach ($definitionsArray['roles'] as $name => $role) {
                $roleObj = $auth->createRole($name);
                if (isset($role['ruleName']) && !empty($role['ruleName'])) {
                    $roleObj->ruleName = $role['ruleName'];
                }
                $auth->add($roleObj);
                $authModels[$name] = $roleObj;
                $log[] = "Added role: $name";
            }

            // Add relations
            foreach ($definitionsArray['relations'] as $relation) {
                if (isset($relation['parent'], $relation['child']) && isset($authModels[$relation['parent']], $authModels[$relation['child']])) {
                    // Get parent and child items
                    $parent = $authModels[$relation['parent']];
                    $child = $authModels[$relation['child']];
                    if ($parent && $child && !$auth->hasChild($parent, $child)) {
                        $auth->addChild($parent, $child);
                        $log[] = "Added relation: {$parent->name} → {$child->name}";
                    } else {
                        Yii::warning("Relation between '{$relation['parent']}' and '{$relation['child']}' already exists or is invalid.");
                    }
                } else {
                    Yii::warning("Invalid relation structure: " . json_encode($relation));
                }
            }
            // Assign roles to users
            foreach ($definitionsArray['assignments'] as $username => $roles) {
                $user = User::findOne(['username' => $username]);
                if ($user) {
                    foreach ($roles as $roleName) {
                        if ($auth->getRole($roleName)) {
                            $auth->assign($auth->getRole($roleName), $user->id);
                            $log[] = "Assigned role '$roleName' to user '$username'";
                        } else {
                            Yii::warning("Role '$roleName' not found for user '$username'");
                        }
                    }
                } else {
                    Yii::warning("User '$username' not found for assignments");
                }
            }
            $connection->commit();
            Yii::info("RBAC definitions imported successfully from array: " . implode("\n", $log), 'debug');
            return true;
        } catch (\Exception $e) {
            $connection->rollBack();
            Yii::error("Failed to import RBAC definitions: " . $e->getMessage() . "\n" . implode("\n", $log), 'error');
            return false;
        }
    }

    /**
     * Generates a Mermaid.js graph definition for the RBAC structure.
     *
     * @param array $definitions The RBAC definitions array.
     * @return string The Mermaid.js graph definition.
     */
    function generateMermaidRBAC(array $definitions): string
    {
        $lines = ["graph TD"];

        // Map all items (roles, permissions, routes) to display-friendly node labels
        $nodeLabels = [];

        foreach ($definitions['roles'] ?? [] as $name => $item) {
            $label = "👤 {$name}";
            $nodeLabels[$name] = "{$name}[\"$label\"]";
        }

        foreach ($definitions['permissions'] ?? [] as $name => $item) {
            $label = "🧩 {$name}";
            $nodeLabels[$name] = "{$name}[\"$label\"]";
        }

        foreach ($definitions['routes'] ?? [] as $name => $item) {
            $safeName = str_replace('/', '_', trim($name, '/'));
            $label = "🔗 {$name}";
            $nodeLabels[$name] = "route_{$safeName}[\"$label\"]";
        }

        // Draw all relations
        foreach ($definitions['relations'] ?? [] as $rel) {
            $parent = $rel['parent'];
            $child = $rel['child'];

            $p = $nodeLabels[$parent] ?? $parent;
            $c = $nodeLabels[$child] ?? (
                str_starts_with($child, '/')
                ? "route_" . str_replace('/', '_', trim($child, '/')) . "[\"🔗 $child\"]"
                : "{$child}[\"{$child}\"]"
            );

            $lines[] = "    $p --> $c";
        }

        // Draw rule annotations
        foreach ($definitions['permissions'] ?? [] as $name => $perm) {
            if (!empty($perm->ruleName)) {
                $ruleNode = "rule_{$perm->ruleName}[\"🔒 {$perm->ruleName}\"]";
                $permNode = $nodeLabels[$name] ?? $name;
                $lines[] = "    $permNode --> $ruleNode";
            }
        }

        // Draw user → role assignments
        foreach ($definitions['assignments'] ?? [] as $username => $roles) {
            $userNode = "user_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $username);
            $lines[] = "    {$userNode}[\"👤 {$username}\"]";
            foreach ($roles as $role) {
                $roleNode = $nodeLabels[$role] ?? $role;
                $lines[] = "    $userNode --> $roleNode";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Retrieves all parent-child relations from the RBAC manager.
     *
     * @param \yii\rbac\ManagerInterface $auth The RBAC manager instance.
     * @return array An array of parent-child relations.
     */
    protected function getAllAuthRelations(\yii\rbac\ManagerInterface $auth): array
    {
        $relations = [];

        $allItems = array_merge(
            $auth->getRoles(),
            $auth->getPermissions()
        );

        foreach ($allItems as $parent) {
            $children = $auth->getChildren($parent->name);
            foreach ($children as $child) {
                $relations[] = [
                    'parent' => $parent->name,
                    'child' => $child->name,
                ];
            }
        }

        return $relations;
    }
}
