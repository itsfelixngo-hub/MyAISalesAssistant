const dotenv = require('dotenv');
dotenv.config();

const Joi = require('joi');

const envValidationSchema = Joi.object({
  MONGO_USER: Joi.string().required(),
  MONGO_PASS: Joi.string().required(),
  MONGO_HOST: Joi.string().default('localhost'),
  MONGO_PORT: Joi.number().default(27017),
  MONGO_DBNAME: Joi.string().required(),
  MONGO_AUTH_SOURCE: Joi.string().default('admin'),
}).unknown(true);

const { error, value: envVars } = envValidationSchema.validate(process.env);
if (error) {
  throw new Error(`Config validation error: ${error.message}`);
}

const config = {
  mongodb: {
    // TODO Change (or review) the url to your MongoDB:
    url: `mongodb://${envVars.MONGO_USER}:${envVars.MONGO_PASS}@${envVars.MONGO_HOST}:${envVars.MONGO_PORT}/${envVars.MONGO_DBNAME}?authSource=${envVars.MONGO_AUTH_SOURCE}`,

    // TODO Change this to your database name:
    databaseName: envVars.MONGO_DBNAME,

    options: {
      //useNewUrlParser: true, // removes a deprecation warning when connecting
      //useUnifiedTopology: true, // removes a deprecating warning when connecting
      //   connectTimeoutMS: 3600000, // increase connection timeout to 1 hour
      //   socketTimeoutMS: 3600000, // increase socket timeout to 1 hour
    }
  },

  // The migrations dir, can be an relative or absolute path. Only edit this when really necessary.
  migrationsDir: "./migrations",

  // The mongodb collection where the applied changes are stored. Only edit this when really necessary.
  changelogCollectionName: "changelog",

  // The mongodb collection where the lock will be created.
  lockCollectionName: "changelog_lock",

  // The value in seconds for the TTL index that will be used for the lock. Value of 0 will disable the feature.
  lockTtl: 0,

  // The file extension to create migrations and search for in migration dir 
  migrationFileExtension: ".js",

  // Enable the algorithm to create a checksum of the file contents and use that in the comparison to determine
  // if the file should be run.  Requires that scripts are coded to be run multiple times.
  useFileHash: false,

  // Don't change this, unless you know what you're doing
  moduleSystem: 'commonjs',
};

module.exports = config;
