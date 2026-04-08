import Joi from 'joi';

export const envValidationSchema = Joi.object({
  NODE_ENV: Joi.string().valid('development', 'production', 'test').default('development'),
  MONGO_USER: Joi.string().required(),
  MONGO_PASS: Joi.string().required(),
  MONGO_HOST: Joi.string().default('localhost'),
  MONGO_PORT: Joi.number().default(27017),
  MONGO_DBNAME: Joi.string().required(),
  MONGO_AUTH_SOURCE: Joi.string().default('admin'),
  AWS_ACCESS_KEY_ID: Joi.string(),
  AWS_SECRET_ACCESS_KEY: Joi.string(),
  AWS_REGION: Joi.string(),
  AWS_BUCKET: Joi.string(),
  JWT_SECRET: Joi.string().default('mySuperSecretKey'),
  JWT_EXPIRES_IN: Joi.string().default('1h'),
  PORT: Joi.number().default(3000),
  SERVER_NAME_PROXY: Joi.string().default('https://api.testproxy'),
  BLOCK_IP: Joi.boolean().default(false),
  BLACK_LIST_IP: Joi.string().default(';'),
  LOG_REQUEST: Joi.boolean().default(false),
  SYNC_INDEX_MONGO: Joi.boolean().default(false)
});