Bundle Configuration:

```yaml
dynamite:
  tables:
    default:
      table_name: '%ddb_table_name%'
      partition_key_name: pk
      sort_key_name: sk
      managed_items:
        - Your\Entity\Name\Goes\Here
      indexes:
        GSI1:
          pk: gsi1pk
          sk: gsi1sk
```

Defining a repository as a service:

somewhere in your `services.yaml`:

```yaml
Your\Repository\Name\Goes\here:
  factory: ['@dynamite.registry', getItemRepository]
  arguments:
    - Your\Entity\Name\Goes\Here
```